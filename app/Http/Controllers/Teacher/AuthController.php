<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('teacher')->check()) {
            return redirect()->route('teacher.dashboard');
        }

        return view('teacher.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        $key = 'teacher-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return back()->withErrors(['email' => 'Đăng nhập sai quá nhiều lần, thử lại sau '.ceil(RateLimiter::availableIn($key) / 60).' phút.'])->onlyInput('email');
        }

        if (! Auth::guard('teacher')->attempt($data + ['is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 600);

            return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản đã bị khoá.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        Auth::guard('teacher')->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('teacher.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('teacher')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('teacher.login');
    }
}
