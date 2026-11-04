<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DbSync\DatabaseSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * API đồng bộ CSDL. Mọi request phải gửi header:
 *   Authorization: Bearer <DB_SYNC_TOKEN>
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
            'tables' => $tables,
        ]);
    }

    /** GET /api/db-sync/export?tables[]=posts — tải dữ liệu máy chủ về. */
    public function export(Request $request): Response
    {
        try {
            $tables = $this->sync->resolveTables(
                (array) $request->query('tables', []),
                (array) $request->query('exclude', []),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response($this->sync->export($tables), 200, [
            'Content-Type' => 'application/gzip',
        ]);
    }

    /**
     * POST /api/db-sync/import — body là payload gzip (application/gzip).
     * Query: migrate=1 (mặc định) để chạy migration còn thiếu trước khi nhập.
     */
    public function import(Request $request): JsonResponse
    {
        @set_time_limit(0);

        try {
            $payload = $this->sync->decode($request->getContent());
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Payload không hợp lệ: '.$e->getMessage()], 422);
        }

        $migrated = null;
        if ($request->boolean('migrate', true)) {
            Artisan::call('migrate', ['--force' => true]);
            $migrated = trim(Artisan::output());
        }

        $missing = $this->sync->missingMigrations($payload);
        if ($missing) {
            return response()->json([
                'ok' => false,
                'message' => 'Máy chủ thiếu migration so với máy gửi. Hãy deploy code mới nhất lên máy chủ trước.',
                'missing_migrations' => $missing,
            ], 409);
        }

        try {
            $result = $this->sync->import($payload);
        } catch (Throwable $e) {
            Log::error('DB sync import failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => 'Nhập dữ liệu thất bại, đã hoàn tác: '.$e->getMessage()], 500);
        }

        Log::info('DB sync import done', [
            'ip' => $request->ip(),
            'source' => $payload['meta']['source'] ?? null,
            'tables' => $result['tables'],
        ]);

        return response()->json([
            'ok' => true,
            'migrate_output' => $migrated,
            'tables' => $result['tables'],
            'skipped' => $result['skipped'],
            'backup' => $result['backup'] ? basename($result['backup']) : null,
        ]);
    }
}
