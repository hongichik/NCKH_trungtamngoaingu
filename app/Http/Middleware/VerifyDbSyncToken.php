<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDbSyncToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('dbsync.token');

        // Chưa cấu hình token => coi như tính năng không tồn tại.
        if ($token === '') {
            abort(404);
        }

        if (strlen($token) < 32) {
            return response()->json(['ok' => false, 'message' => 'DB_SYNC_TOKEN phải dài tối thiểu 32 ký tự.'], 503);
        }

        if (! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
