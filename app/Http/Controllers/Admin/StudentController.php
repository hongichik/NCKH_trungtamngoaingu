<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $students = Student::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $keyword = $request->string('q');
                $query->where(function ($q) use ($keyword) {
                    $q->where('first_name', 'like', "%{$keyword}%")
                        ->orWhere('last_name', 'like', "%{$keyword}%")
                        ->orWhere('identity_number', 'like', "%{$keyword}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.students.index', compact('students'));
    }

    public function create(): View
    {
        return view('admin.students.form', ['student' => new Student]);
    }

    public function store(Request $request): RedirectResponse
    {
        Student::create($this->validateData($request));

        return redirect()->route('admin.students.index')->with('status', 'Đã thêm học viên.');
    }

    public function edit(Student $student): View
    {
        return view('admin.students.form', compact('student'));
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $student->update($this->validateData($request));

        return redirect()->route('admin.students.index')->with('status', 'Đã cập nhật học viên.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()->route('admin.students.index')->with('status', 'Đã xoá học viên.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'numbering' => ['nullable', 'string', 'max:60'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'candidate_number' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:25'],
            'dob' => ['nullable', 'date'],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'identity_number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:published,draft'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
