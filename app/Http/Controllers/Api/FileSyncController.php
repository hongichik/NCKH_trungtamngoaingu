<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DbSync\DatabaseSync;
use App\Support\DbSync\FileSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API đồng bộ thư mục (ảnh, file upload...). Yêu cầu token như API CSDL
 * và tính năng phải được bật (DB_SYNC_FILES_ENABLED=true).
 */
class FileSyncController extends Controller
{
    public function __construct(private FileSync $files) {}

    /** GET /api/db-sync/files/manifest — danh sách file + kích thước + mtime. */
    public function manifest(Request $request): JsonResponse
    {
        @set_time_limit(0);

        $dirs = $request->query('dirs');

        return response()->json([
            'ok' => true,
            'dirs' => $this->files->dirs(),
            'files' => $this->files->manifest(is_array($dirs) ? $dirs : null),
        ]);
    }

    /** POST /api/db-sync/files/put?path=...&mtime=... — body là nội dung file. */
    public function put(Request $request): JsonResponse
    {
        $path = (string) $request->query('path');
        if (! $abs = $this->files->resolve($path)) {
            return response()->json(['ok' => false, 'message' => "Đường dẫn không được phép: {$path}"], 422);
        }

        $bytes = $this->files->write($abs, $request->getContent(true), $request->integer('mtime') ?: null);

        return response()->json(['ok' => true, 'path' => $path, 'bytes' => $bytes]);
    }

    /** GET /api/db-sync/files/get?path=... — tải 1 file. */
    public function get(Request $request): Response
    {
        $path = (string) $request->query('path');
        $abs = $this->files->resolve($path);

        if (! $abs || ! is_file($abs)) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy file.'], 404);
        }

        return response()->file($abs, ['X-File-Mtime' => (string) filemtime($abs)]);
    }

    /** POST /api/db-sync/files/delete — JSON {"paths": [...]}. */
    public function delete(Request $request): JsonResponse
    {
        $deleted = [];
        foreach ((array) $request->input('paths', []) as $path) {
            $abs = $this->files->resolve((string) $path);
            if ($abs && is_file($abs) && @unlink($abs)) {
                $deleted[] = $path;
            }
        }

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }

    /** POST /api/db-sync/files/report — máy gửi báo kết quả để ghi nhật ký. */
    public function report(Request $request, DatabaseSync $sync): JsonResponse
    {
        $sync->log('files-push', [
            'ip' => $request->ip(),
            'source' => mb_substr((string) $request->input('source'), 0, 200),
            'uploaded' => (int) $request->input('uploaded'),
            'deleted' => (int) $request->input('deleted'),
            'failed' => (int) $request->input('failed'),
        ]);

        return response()->json(['ok' => true]);
    }
}
