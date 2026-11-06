<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassroomCustomExercise;
use App\Models\CustomAttempt;
use App\Models\CustomExercise;
use App\Models\CustomExerciseItem;
use App\Support\CustomExercise\CustomExerciseService;
use App\Support\Video\VideoResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Học viên làm bài tập tuỳ chỉnh được giao trong lớp. */
class CustomExerciseController extends Controller
{
    public function __construct(private CustomExerciseService $service) {}

    private function student()
    {
        return auth('student')->user();
    }

    private function own(CustomAttempt $attempt): CustomAttempt
    {
        abort_unless($attempt->student_id === $this->student()->id, 404);

        return $attempt;
    }

    /** Làm bài tập của bài học: tạo cài đặt của lớp cho bài (nếu chưa có) rồi bắt đầu. */
    public function startExercise(Classroom $classroom, CustomExercise $exercise): RedirectResponse
    {
        abort_unless($exercise->status === 'published' && $exercise->course_id === $classroom->course_id
            && (! $exercise->source_classroom_id || $exercise->source_classroom_id === $classroom->id), 404);
        $cce = ClassroomCustomExercise::firstOrCreate(['classroom_id' => $classroom->id, 'custom_exercise_id' => $exercise->id], ['lesson_id' => $exercise->lesson_id]);

        return $this->start($cce);
    }

    public function start(ClassroomCustomExercise $cce): RedirectResponse
    {
        [$attempt, $error] = $this->service->start($cce, $this->student());
        if (! $attempt) {
            return back()->with('status_error', $error);
        }

        return redirect()->route('portal.custom.do', $attempt);
    }

    public function show(CustomAttempt $attempt): View|RedirectResponse
    {
        $this->own($attempt)->load('assignment.exercise.items', 'assignment.classroom');
        if ($attempt->status !== 'in_progress') {
            return redirect()->route('portal.custom.result', $attempt);
        }
        abort_unless($attempt->assignment->classroom->accessOpen(), 403, $attempt->assignment->classroom->closedReason());

        return view('custom.portal.do', ['attempt' => $attempt, 'cce' => $attempt->assignment, 'exercise' => $attempt->assignment->exercise]);
    }

    /** Lưu nháp câu trả lời (tự lưu định kỳ) để mất mạng / tải lại trang không mất bài. */
    public function save(Request $request, CustomAttempt $attempt): JsonResponse
    {
        $this->own($attempt);
        abort_unless($attempt->status === 'in_progress', 409);
        $attempt->update(['answers' => (array) json_decode((string) $request->input('answers'), true)]);

        return response()->json(['ok' => true]);
    }

    public function submit(Request $request, CustomAttempt $attempt): RedirectResponse
    {
        $this->own($attempt);
        if ($attempt->status !== 'in_progress') {
            return redirect()->route('portal.custom.result', $attempt);
        }
        $answers = (array) json_decode((string) $request->input('answers'), true);
        $this->service->submit($attempt, $answers);

        return redirect()->route('portal.custom.result', $attempt)->with('status', 'Đã nộp bài.');
    }

    /** Ghi âm phần Nói (tải lên ngay khi dừng ghi). */
    public function record(Request $request, CustomAttempt $attempt, CustomExerciseItem $item): JsonResponse
    {
        $this->own($attempt);
        abort_unless($attempt->status === 'in_progress' && $item->custom_exercise_id === $attempt->assignment->custom_exercise_id, 404);
        $request->validate(['audio' => ['required', 'file', 'max:15360', 'mimetypes:audio/webm,video/webm,audio/mp4,video/mp4,audio/x-m4a,audio/m4a,audio/ogg,audio/wav,audio/x-wav,audio/mpeg,application/octet-stream']]);
        $ext = in_array($e = strtolower($request->file('audio')->getClientOriginalExtension()), ['webm', 'm4a', 'mp4', 'ogg', 'wav'], true) ? $e : 'webm';
        $path = $request->file('audio')->storeAs("recordings/custom-{$attempt->id}", $item->id.'.'.$ext, 'local');
        $attempt->update(['files' => array_replace($attempt->files ?? [], [$item->id => $path])]);

        return response()->json(['ok' => true]);
    }

    public function result(CustomAttempt $attempt): View
    {
        $this->own($attempt)->load('assignment.exercise.items', 'assignment.classroom');

        return view('custom.portal.result', ['attempt' => $attempt, 'cce' => $attempt->assignment, 'exercise' => $attempt->assignment->exercise,
            'showAnswers' => $attempt->assignment->answersVisible()]);
    }

    public function status(CustomAttempt $attempt): JsonResponse
    {
        $this->own($attempt);

        return response()->json(['status' => $attempt->status, 'score' => $attempt->score]);
    }

    /** File nghe của một phần (chỉ học viên đang làm / đã làm bài này). */
    public function audio(CustomAttempt $attempt, CustomExerciseItem $item): BinaryFileResponse
    {
        $this->own($attempt);
        abort_unless($item->custom_exercise_id === $attempt->assignment->custom_exercise_id && $item->audio_path, 404);

        return VideoResponse::file(Storage::disk('local')->path($item->audio_path));
    }

    /** Ảnh của một phần (chỉ học viên đang làm / đã làm bài này). */
    public function image(CustomAttempt $attempt, CustomExerciseItem $item): BinaryFileResponse
    {
        $this->own($attempt);
        abort_unless($item->custom_exercise_id === $attempt->assignment->custom_exercise_id && $item->image_path, 404);

        return response()->file(Storage::disk('local')->path($item->image_path), ['Cache-Control' => 'private, max-age=3600']);
    }
}
