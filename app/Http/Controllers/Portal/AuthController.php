<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        if (! Auth::guard('student')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], true)) {
            return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng.'])->onlyInput('email');
        }

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
