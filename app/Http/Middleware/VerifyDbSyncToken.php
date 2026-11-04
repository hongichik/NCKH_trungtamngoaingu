<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bảo vệ API đồng bộ bằng token.
 * Tham số: "db" (đồng bộ CSDL) hoặc "files" (đồng bộ thư mục) để kiểm tra
 * tính năng tương ứng có đang bật không; "any" chỉ kiểm tra token.
 */
class VerifyDbSyncToken
{
    public function handle(Request $request, Closure $next, string $feature = 'any'): Response
    {
        $token = (string) config('dbsync.token');

        // Chưa cấu hình token => coi như tính năng không tồn tại.
        if ($token === '') {
            abort(404);
        }

        if (strlen($token) < 32) {
            return response()->json(['ok' => false, 'message' => 'DB_SYNC_TOKEN phải dài tối thiểu 32 ký tự.'], 503);
        }

        // Chỉ giới hạn các lần gửi sai token (chống dò token), không giới hạn request hợp lệ.
        $key = 'dbsync-auth:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['ok' => false, 'message' => 'Sai token quá nhiều lần, thử lại sau.'], 429);
        }

        if (! hash_equals($token, (string) $request->bearerToken())) {
            RateLimiter::hit($key, 600);

            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $disabled = match ($feature) {
            'db' => ! config('dbsync.enabled'),
            'files' => ! config('dbsync.files_enabled'),
            default => false,
        };

        if ($disabled) {
            $label = $feature === 'db' ? 'Đồng bộ CSDL' : 'Đồng bộ thư mục';

            return response()->json(['ok' => false, 'message' => "{$label} đang bị tắt trên máy này (bật trong Admin > Quản trị hệ thống > Đồng bộ)."], 403);
        }

        return $next($request);
    }
}
