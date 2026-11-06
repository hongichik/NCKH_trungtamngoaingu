<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cổng giáo viên: phải đăng nhập bằng guard "teacher" và tài khoản đang hoạt động.
 * Trong khu này auth()->user() trả về giáo viên.
 */
class EnsureTeacher
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('teacher');

        if (! $guard->check() || ! $guard->user()->is_active) {
            if ($guard->check()) {
                $guard->logout();
            }
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Phiên đăng nhập đã hết hạn, hãy đăng nhập lại.'], 401);
            }
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('teacher.login');
        }

        // Trong khu giáo viên auth()->user() là giáo viên; trả lại guard cũ sau request
        // để không ảnh hưởng chỗ khác (vd khi chạy test nhiều request liên tiếp).
        $previous = Auth::getDefaultDriver();
        Auth::shouldUse('teacher');
        try {
            return $next($request);
        } finally {
            Auth::shouldUse($previous);
        }
    }
}
