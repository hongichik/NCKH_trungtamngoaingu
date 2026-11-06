<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DbSync\DatabaseSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * API đồng bộ CSDL. Mọi request phải gửi header:
 *   Authorization: Bearer <DB_SYNC_TOKEN>
 *
 * Quy trình đẩy dữ liệu lên (push):
 *   1. POST /chunk?id=<phiên>&seq=1..n   (body: 1 phần gzip NDJSON) — chỉ lưu tạm, chưa đụng DB
 *   2. POST /commit?id=<phiên>&migrate=1 — migrate, sao lưu, nhập toàn bộ trong 1 transaction
 */
class DbSyncController extends Controller
{
    public function __construct(private DatabaseSync $sync) {}

    /** GET /api/db-sync/status — kiểm tra kết nối & token. */
    public function status(): JsonResponse
    {
        $tables = [];
        foreach ($this->sync->syncableTables() as $table) {
            $tables[$table] = DB::table($table)->count();
        }

        return response()->json([
            'ok' => true,
            'app' => config('app.name'),
            'env' => app()->environment(),
            'driver' => DB::getDriverName(),
            'format' => DatabaseSync::FORMAT_VERSION,
            'db_sync_enabled' => (bool) config('dbsync.enabled'),
            'files_sync_enabled' => (bool) config('dbsync.files_enabled'),
            'limits' => [
                'memory_limit' => ini_get('memory_limit'),
                'post_max_size' => ini_get('post_max_size'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'tables' => $tables,
            'ai_jobs' => \App\Models\AiJob::selectRaw('type, status, COUNT(*) AS c')->groupBy('type', 'status')->get()
                ->groupBy('type')->map(fn ($g) => $g->pluck('c', 'status')),
            'ai_recent_errors' => \App\Models\AiJob::whereNotNull('last_error')->latest('id')->limit(5)->get(['id', 'type', 'status', 'last_error', 'updated_at']),
            'ai_heartbeat' => \App\Support\Ai\Jobs\AiJobRunner::lastHeartbeat(),
        ]);
    }

    /** GET /api/db-sync/export?tables[]=posts — tải dữ liệu máy chủ về (stream gzip). */
    public function export(Request $request): Response
    {
        try {
            $tables = $this->sync->resolveTables(
                (array) $request->query('tables', []),
                (array) $request->query('exclude', []),
                $request->boolean('include_protected'),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        @set_time_limit(0);

        return response()->stream(function () use ($tables) {
            $ctx = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
            foreach ($this->sync->lines($tables) as $line) {
                $out = deflate_add($ctx, $line."\n", ZLIB_NO_FLUSH);
                if ($out !== '') {
                    echo $out;
                }
            }
            echo deflate_add($ctx, '', ZLIB_FINISH);
        }, 200, [
            'Content-Type' => 'application/gzip',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** POST /api/db-sync/chunk?id=<32 hex>&seq=<n> — nhận 1 phần dữ liệu. */
    public function chunk(Request $request): JsonResponse
    {
        if (! $dir = $this->sessionDir($request)) {
            return response()->json(['ok' => false, 'message' => 'Tham số id không hợp lệ.'], 422);
        }

        $seq = (int) $request->query('seq');
        if ($seq < 1 || $seq > 99999) {
            return response()->json(['ok' => false, 'message' => 'Tham số seq không hợp lệ.'], 422);
        }

        $this->pruneSessions();
        File::ensureDirectoryExists($dir);

        $path = sprintf('%s/part-%05d.gz', $dir, $seq);
        $in = $request->getContent(true);
        $out = fopen($path, 'wb');
        $bytes = stream_copy_to_stream($in, $out);
        fclose($out);

        $magic = file_get_contents($path, false, null, 0, 2);
        if ($bytes < 2 || $magic !== "\x1f\x8b") {
            File::delete($path);

            return response()->json(['ok' => false, 'message' => 'Phần dữ liệu không phải gzip (có thể vượt post_max_size của máy chủ).'], 422);
        }

        return response()->json(['ok' => true, 'seq' => $seq, 'bytes' => $bytes]);
    }

    /** Lệnh artisan chạy được qua API (chỉ danh sách này, tham số được kiểm tra). */
    private const RUNNABLE = ['demo:learning-data' => ['teacher', 'student']];

    /** POST /api/db-sync/run {command, options:{...}} — chạy lệnh trong danh sách cho phép (hosting không có SSH). */
    public function run(Request $request): JsonResponse
    {
        $data = $request->validate([
            'command' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(self::RUNNABLE))],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:190'],
        ]);
        $params = [];
        foreach (self::RUNNABLE[$data['command']] as $opt) {
            if (filled($data['options'][$opt] ?? null)) {
                $params['--'.$opt] = $data['options'][$opt];
            }
        }
        @set_time_limit(120);
        $code = Artisan::call($data['command'], $params);
        Log::info('DB sync run', ['command' => $data['command'], 'params' => $params, 'code' => $code, 'ip' => $request->ip()]);

        return response()->json(['ok' => $code === 0, 'exit_code' => $code, 'output' => trim(Artisan::output())], $code === 0 ? 200 : 500);
    }

    /** POST /api/db-sync/commit?id=<32 hex>&migrate=1 — nhập các phần đã nhận. */
    public function commit(Request $request): JsonResponse
    {
        @set_time_limit(0);

        if (! $dir = $this->sessionDir($request)) {
            return response()->json(['ok' => false, 'message' => 'Tham số id không hợp lệ.'], 422);
        }

        $parts = collect(File::glob($dir.'/part-*.gz'))->sort()->values()->all();
        if (! $parts) {
            return response()->json(['ok' => false, 'message' => 'Không có dữ liệu nào được gửi trong phiên này.'], 422);
        }

        try {
            try {
                $meta = $this->sync->readMeta($parts);
            } catch (Throwable $e) {
                return response()->json(['ok' => false, 'message' => 'Dữ liệu không hợp lệ: '.$e->getMessage()], 422);
            }

            $migrated = null;
            if ($request->boolean('migrate', true)) {
                Artisan::call('migrate', ['--force' => true]);
                $migrated = trim(Artisan::output());
            }

            if ($missing = $this->sync->missingMigrations($meta)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Máy chủ thiếu migration so với máy gửi. Hãy deploy code mới nhất lên máy chủ trước.',
                    'missing_migrations' => $missing,
                ], 409);
            }

            try {
                // include_protected=1 (db:sync-push --include-protected): ghi đè cả bảng được bảo vệ (giáo viên, khoá, lớp...)
                $result = $this->sync->import($parts, true, $request->boolean('include_protected'));
            } catch (Throwable $e) {
                Log::error('DB sync import failed', ['error' => $e->getMessage()]);

                return response()->json(['ok' => false, 'message' => 'Nhập dữ liệu thất bại, đã hoàn tác: '.$e->getMessage()], 500);
            }
        } finally {
            File::deleteDirectory($dir);
        }

        Log::info('DB sync import done', [
            'ip' => $request->ip(),
            'source' => $meta['source'] ?? null,
            'tables' => $result['tables'],
        ]);

        $this->sync->log('db-push', [
            'ip' => $request->ip(),
            'source' => $meta['source'] ?? null,
            'tables' => $result['tables'],
            'backup' => $result['backup'] ? basename($result['backup']) : null,
        ]);

        return response()->json([
            'ok' => true,
            'migrate_output' => $migrated,
            'tables' => $result['tables'],
            'skipped' => $result['skipped'],
            'backup' => $result['backup'] ? basename($result['backup']) : null,
        ]);
    }

    private function sessionDir(Request $request): ?string
    {
        $id = (string) $request->query('id');

        return preg_match('/^[a-f0-9]{32}$/', $id) ? storage_path('app/db-sync-sessions/'.$id) : null;
    }

    /** Xoá các phiên upload dở dang quá 1 ngày. */
    private function pruneSessions(): void
    {
        $root = storage_path('app/db-sync-sessions');
        if (! is_dir($root)) {
            return;
        }

        foreach (File::directories($root) as $dir) {
            if (filemtime($dir) < time() - 86400) {
                File::deleteDirectory($dir);
            }
        }
    }
}
