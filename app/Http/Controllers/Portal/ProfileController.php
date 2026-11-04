<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        return view('portal.profile', [
            'student' => $student,
            'type' => in_array($request->query('type'), ['hsk', 'cntt'], true) ? $request->query('type') : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['Nam', 'Nữ', 'Khác'])],
            'phone' => ['required', 'string', 'max:25'],
            'dob' => ['required', 'date'],
            'email' => ['required', 'email', 'max:255', Rule::unique('students', 'email')->ignore($student->id)],
            'identity_number' => ['required', 'string', 'max:100'],
            'identity_date' => ['required', 'date'],
            'identity_place' => ['required', Rule::in(Student::IDENTITY_PLACE_OPTIONS)],
            'address_province' => ['required', 'string', 'max:120'],
            'address_ward' => ['required', 'string', 'max:120'],
            'address_detail' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'identity_front_image' => ['nullable', 'image', 'max:4096'],
            'identity_back_image' => ['nullable', 'image', 'max:4096'],

            // HSK-only block: never required to save the profile itself. A missing
            // field here only blocks the HSK exam-registration step, not this save.
            'chinese_name' => ['nullable', 'string', 'max:255'],
            'ethnic' => ['nullable', 'string', 'max:120'],
            'religion' => ['nullable', 'string', 'max:120'],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:120'],
            'mother_tongue' => ['nullable', 'string', 'max:120'],
            'chinese_years' => ['nullable', 'integer', 'min:0', 'max:99'],

            'type' => ['nullable', Rule::in(['hsk', 'cntt'])],
        ]);

        $data['last_name'] = $data['full_name'];
        unset($data['full_name']);

        foreach (['avatar', 'identity_front_image', 'identity_back_image'] as $field) {
            if ($request->hasFile($field)) {
                if ($student->{$field}) {
                    Storage::disk('public')->delete($student->{$field});
                }
                $data[$field] = $request->file($field)->store('students', 'public');
            }
        }

        $type = $data['type'] ?? null;
        unset($data['type']);

        $student->update($data);

        if ($student->hasCompletedCoreProfile() && $type) {
            return redirect()->route('portal.exam.show', ['type' => $type])
                ->with('status', 'Đã lưu thông tin thí sinh.');
        }

        return redirect()->route('portal.profile.edit', $type ? ['type' => $type] : [])
            ->with('status', 'Đã lưu thông tin thí sinh.');
    }
}
