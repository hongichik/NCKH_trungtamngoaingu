<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin quản lý tài khoản giáo viên (giáo viên đăng nhập ở cổng /giao-vien). */
class TeacherController extends Controller
{
    public function index(): View
    {
        return view('admin.teachers.index', ['teachers' => Teacher::withCount('textbooks')->orderBy('name')->paginate(30)]);
    }

    public function create(): View
    {
        return view('admin.teachers.form', ['teacher' => new Teacher(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Teacher::create($this->validated($request));

        return redirect()->route('admin.teachers.index')->with('status', 'Đã tạo tài khoản giáo viên. Giáo viên đăng nhập tại '.route('teacher.login'));
    }

    public function edit(Teacher $teacher): View
    {
        return view('admin.teachers.form', compact('teacher'));
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $this->validated($request, $teacher);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $teacher->update($data);

        return redirect()->route('admin.teachers.index')->with('status', 'Đã cập nhật tài khoản giáo viên.');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        $teacher->delete();

        return redirect()->route('admin.teachers.index')->with('status', 'Đã xoá tài khoản giáo viên (giáo trình của giáo viên vẫn được giữ lại).');
    }

    private function validated(Request $request, ?Teacher $teacher = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('teachers', 'email')->ignore($teacher?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$teacher ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
