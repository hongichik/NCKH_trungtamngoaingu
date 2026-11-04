<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ExamLevel;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExamRegistrationController extends Controller
{
    public function show(Request $request, string $type): View
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $sessions = ExamSession::type($type)->openForRegistration()
            ->withCount(['activeRegistrations as registrations_count'])
            ->orderBy('exam_date')
            ->get();
        $levels = ExamLevel::type($type)->active()->orderBy('order')->get();

        $registrations = $student->examRegistrations()
            ->type($type)
            ->with(['examSession', 'examLevel'])
            ->latest()
            ->get();

        return view('portal.exam-registration', [
            'type' => $type,
            'typeName' => $type === 'hsk' ? 'HSK' : 'Chứng chỉ ứng dụng CNTT',
            'sessions' => $sessions,
            'levels' => $levels,
            'registrations' => $registrations,
            'student' => $student,
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        if (! $student->hasCompletedCoreProfile()) {
            return redirect()->route('portal.profile.edit', ['type' => $type])
                ->with('status_error', 'Vui lòng bổ sung đầy đủ thông tin thí sinh trước khi đăng ký dự thi.');
        }

        if ($type === 'hsk' && ! $student->hasCompletedHskProfile()) {
            return redirect()->route('portal.profile.edit', ['type' => $type])
                ->with('status_error', 'Vui lòng bổ sung thông tin dành riêng cho thí sinh đăng ký thi HSK trước khi đăng ký dự thi.');
        }

        $data = $request->validate([
            'exam_session_id' => ['required', 'integer', 'exists:exam_sessions,id'],
            'exam_level_id' => ['required', 'integer', 'exists:exam_levels,id'],
        ]);

        $level = ExamLevel::type($type)->active()->findOrFail($data['exam_level_id']);

        $result = DB::transaction(function () use ($type, $data, $student, $level) {
            // Lock the session row so two students racing for the last seat can't both succeed.
            $session = ExamSession::type($type)->openForRegistration()
                ->lockForUpdate()
                ->findOrFail($data['exam_session_id']);

            $alreadyRegistered = ExamRegistration::where('student_id', $student->id)
                ->where('exam_session_id', $session->id)
                ->where('exam_level_id', $level->id)
                ->exists();

            if ($alreadyRegistered) {
                return 'duplicate';
            }

            if ($session->capacity !== null && $session->activeRegistrations()->count() >= $session->capacity) {
                return 'full';
            }

            ExamRegistration::create([
                'registration_code' => $this->generateCode($type),
                'student_id' => $student->id,
                'exam_session_id' => $session->id,
                'exam_level_id' => $level->id,
                'exam_type' => $type,
                'price' => $level->price,
                'status' => 'pending_payment',
            ]);

            return 'ok';
        });

        if ($result === 'duplicate') {
            return back()->with('status_error', 'Bạn đã đăng ký cấp độ này trong đợt thi đã chọn.');
        }

        if ($result === 'full') {
            return back()->with('status_error', 'Đợt thi này đã đủ số lượng đăng ký. Vui lòng chọn đợt thi khác.');
        }

        return redirect()->route('portal.exam.show', ['type' => $type])
            ->with('status', 'Đăng ký dự thi thành công. Vui lòng thanh toán lệ phí để giữ chỗ.');
    }

    protected function generateCode(string $type): string
    {
        do {
            $code = strtoupper($type).'-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
        } while (ExamRegistration::where('registration_code', $code)->exists());

        return $code;
    }
}
