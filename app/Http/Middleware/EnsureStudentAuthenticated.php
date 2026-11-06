<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentAuthenticated
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('student')->check()) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('portal.auth');
        }

        if (Auth::guard('student')->user()->login_locked) {
            Auth::guard('student')->logout();
            $request->session()->invalidate();

            return redirect()->route('portal.auth')->withErrors(['email' => 'Tài khoản đã bị khoá.']);
        }

        // Tài khoản do quản trị viên / giáo viên tạo hoặc vừa được cấp lại mật khẩu: phải đổi mật khẩu trước.
        if (Auth::guard('student')->user()->must_change_password && ! $request->routeIs('portal.password.*', 'portal.logout')) {
            return redirect()->route('portal.password.edit');
        }

        return $next($request);
    }
}
