<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chỉ chủ hệ thống (tài khoản id = 1) được đi qua.
 */
class EnsureSystemOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isOwner()) {
            abort(403, 'Chỉ chủ hệ thống mới truy cập được trang này.');
        }

        return $next($request);
    }
}
