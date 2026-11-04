<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::withCount('permissions')->orderBy('id')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => new User,
            'modules' => config('permissions.modules'),
            'actionLabels' => config('permissions.actions'),
            'permissions' => Permission::all(),
            'selectedPermissionIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => false,
        ]);

        $user->permissions()->sync($data['permissions'] ?? []);

        return redirect()->route('admin.users.index')->with('status', 'Đã tạo tài khoản.');
    }

    public function edit(User $user): View
    {
        $this->preventEditingSuperAdmin($user);

        return view('admin.users.form', [
            'user' => $user,
            'modules' => config('permissions.modules'),
            'actionLabels' => config('permissions.actions'),
            'permissions' => Permission::all(),
            'selectedPermissionIds' => $user->permissions->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->preventEditingSuperAdmin($user);

        $data = $this->validateData($request, $user);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            ...(filled($data['password'] ?? null) ? ['password' => Hash::make($data['password'])] : []),
        ]);

        $user->permissions()->sync($data['permissions'] ?? []);

        return redirect()->route('admin.users.index')->with('status', 'Đã cập nhật tài khoản.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->preventEditingSuperAdmin($user);

        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages(['user' => 'Không thể xoá chính tài khoản đang đăng nhập.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'Đã xoá tài khoản.');
    }

    protected function preventEditingSuperAdmin(User $user): void
    {
        if ($user->is_admin) {
            abort(403, 'Không thể chỉnh sửa tài khoản admin cao nhất.');
        }
    }

    protected function validateData(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        return $data;
    }
}
