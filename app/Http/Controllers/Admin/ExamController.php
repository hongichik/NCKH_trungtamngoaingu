<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamLevel;
use App\Models\ExamSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->get('type') === 'cntt' ? 'cntt' : 'hsk';

        return view('admin.exams.index', [
            'type' => $type,
            'sessions' => ExamSession::type($type)
                ->withCount(['activeRegistrations as registrations_count'])
                ->orderByDesc('exam_date')
                ->get(),
            'levels' => ExamLevel::type($type)->orderBy('order')->get(),
        ]);
    }

    // -- Levels (cấp độ & bảng giá) --------------------------------------

    public function storeLevel(Request $request): RedirectResponse
    {
        $data = $this->validateLevel($request);

        ExamLevel::create($data);

        return back()->with('status', 'Đã thêm cấp độ.');
    }

    public function updateLevel(Request $request, ExamLevel $level): RedirectResponse
    {
        $level->update($this->validateLevel($request));

        return back()->with('status', 'Đã cập nhật cấp độ / giá.');
    }

    public function destroyLevel(ExamLevel $level): RedirectResponse
    {
        $level->delete();

        return back()->with('status', 'Đã xoá cấp độ.');
    }

    protected function validateLevel(Request $request): array
    {
        $data = $request->validate([
            'exam_type' => ['required', Rule::in(['hsk', 'cntt'])],
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'integer', 'min:0'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['order'] = $data['order'] ?? 0;

        return $data;
    }

    // -- Sessions (đợt thi) ------------------------------------------------

    public function createSession(Request $request): View
    {
        $type = $request->get('type') === 'cntt' ? 'cntt' : 'hsk';

        return view('admin.exams.session-form', ['session' => new ExamSession(['exam_type' => $type]), 'type' => $type]);
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $data = $this->validateSession($request);

        ExamSession::create($data);

        return redirect()->route('admin.exams.index', ['type' => $data['exam_type']])->with('status', 'Đã tạo đợt thi.');
    }

    public function editSession(ExamSession $session): View
    {
        return view('admin.exams.session-form', ['session' => $session, 'type' => $session->exam_type]);
    }

    public function updateSession(Request $request, ExamSession $session): RedirectResponse
    {
        $session->update($this->validateSession($request));

        return redirect()->route('admin.exams.index', ['type' => $session->exam_type])->with('status', 'Đã cập nhật đợt thi.');
    }

    public function destroySession(ExamSession $session): RedirectResponse
    {
        $type = $session->exam_type;
        $session->delete();

        return redirect()->route('admin.exams.index', ['type' => $type])->with('status', 'Đã xoá đợt thi.');
    }

    protected function validateSession(Request $request): array
    {
        return $request->validate([
            'exam_type' => ['required', Rule::in(['hsk', 'cntt'])],
            'name' => ['required', 'string', 'max:255'],
            'exam_date' => ['nullable', 'date'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'registration_start' => ['nullable', 'date'],
            'registration_end' => ['nullable', 'date', 'after_or_equal:registration_start'],
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);
    }
}
