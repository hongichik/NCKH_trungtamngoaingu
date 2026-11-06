<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $type = $this->validType($request->query('type'));

        if (Auth::guard('student')->check()) {
            return $this->redirectAfterAuth($request, $type);
        }

        return view('portal.auth', [
            'type' => $type,
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('students', 'email')],
            'phone' => ['required', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'type' => ['nullable', Rule::in(['hsk', 'cntt'])],
        ]);

        // Chặn tạo tài khoản hàng loạt từ một IP.
        $key = 'portal-register:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Bạn đã đăng ký quá nhiều lần, thử lại sau '.ceil(RateLimiter::availableIn($key) / 60).' phút.'])->withInput();
        }
        RateLimiter::hit($key, 3600);

        $student = Student::create([
            'last_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'status' => 'published',
        ]);

        Auth::guard('student')->login($student);
        $request->session()->regenerate();

        return $this->redirectAfterAuth($request, $data['type'] ?? null);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'type' => ['nullable', Rule::in(['hsk', 'cntt'])],
        ]);

        // Chống dò mật khẩu: tối đa 8 lần sai / 10 phút cho mỗi IP + email.
        $key = 'portal-login:'.$request->ip().'|'.mb_strtolower($credentials['email']);
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return back()->withErrors(['email' => 'Đăng nhập sai quá nhiều lần, thử lại sau '.ceil(RateLimiter::availableIn($key) / 60).' phút.'])->onlyInput('email');
        }

        if (! Auth::guard('student')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], true)) {
            RateLimiter::hit($key, 600);

            return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng.'])->onlyInput('email');
        }
        RateLimiter::clear($key);
        // Tài khoản bị khoá (quản trị viên khoá ở trang Tài khoản học viên).
        if (Auth::guard('student')->user()->login_locked) {
            Auth::guard('student')->logout();

            return back()->withErrors(['email' => 'Tài khoản đã bị khoá. Liên hệ trung tâm để được mở lại.'])->onlyInput('email');
        }
        Auth::guard('student')->user()->forceFill(['last_login_at' => now()])->saveQuietly();

        $request->session()->regenerate();

        return $this->redirectAfterAuth($request, $credentials['type'] ?? null);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    protected function redirectAfterAuth(Request $request, ?string $type): RedirectResponse
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        if ($intended = $request->session()->pull('url.intended')) {
            return redirect()->to($intended);
        }

        // Học viên có lớp học: về trang chủ cổng (lịch học, bài tập) — không ép khai hồ sơ thi.
        if (! $type && $student->classrooms()->exists()) {
            return redirect()->route('portal.home');
        }

        if (! $student->hasCompletedCoreProfile()) {
            return redirect()->route('portal.profile.edit', $type ? ['type' => $type] : []);
        }

        return redirect()->route('portal.exam.show', ['type' => $type ?: 'cntt']);
    }

    protected function validType(?string $type): ?string
    {
        return in_array($type, ['hsk', 'cntt'], true) ? $type : null;
    }
}
