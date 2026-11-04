<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function index(Request $request): View
    {
        $certificates = Certificate::with('student')
            ->when($request->filled('q'), fn ($q) => $q->where('serial_number', 'like', '%'.$request->string('q').'%'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.certificates.index', compact('certificates'));
    }

    public function create(): View
    {
        $students = Student::orderBy('last_name')->get();

        return view('admin.certificates.form', ['certificate' => new Certificate, 'students' => $students]);
    }

    public function store(Request $request): RedirectResponse
    {
        Certificate::create($this->validateData($request));

        return redirect()->route('admin.certificates.index')->with('status', 'Đã thêm chứng chỉ.');
    }

    public function edit(Certificate $certificate): View
    {
        $students = Student::orderBy('last_name')->get();

        return view('admin.certificates.form', compact('certificate', 'students'));
    }

    public function update(Request $request, Certificate $certificate): RedirectResponse
    {
        $certificate->update($this->validateData($request));

        return redirect()->route('admin.certificates.index')->with('status', 'Đã cập nhật chứng chỉ.');
    }

    public function destroy(Certificate $certificate): RedirectResponse
    {
        $certificate->delete();

        return redirect()->route('admin.certificates.index')->with('status', 'Đã xoá chứng chỉ.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'numbering' => ['nullable', 'string', 'max:60'],
            'exam_date' => ['nullable', 'date'],
            'exam_place' => ['nullable', 'string', 'max:255'],
            'certificate_type' => ['nullable', 'string', 'max:255'],
            'classification' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'certificate_number' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'issue_place' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:published,draft'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
