<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Classroom;
use App\Models\ClassroomCustomExercise;
use App\Models\CustomAttempt;
use App\Models\CustomExercise;
use App\Support\CustomExercise\CustomExerciseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bài tập tuỳ chỉnh đã thêm vào lớp: bảng điểm, xem bài làm, chấm phần Viết / Nói, sửa điểm, gỡ khỏi lớp.
 * Lớp dùng trait khai báo: classCustomLayout(), classCustomPrefix(), authorizeClassCustom($classroom, bool $manage).
 */
trait ManagesClassCustom
{
    abstract protected function classCustomLayout(): string;

    abstract protected function classCustomPrefix(): string;

    /** $manage = true: thêm / gỡ bài (cần quyền tuỳ chỉnh); false: xem và chấm (giáo viên được gán). */
    abstract protected function authorizeClassCustom(Classroom $classroom, bool $manage): void;

    private function cceOf(Classroom $classroom, ClassroomCustomExercise $cce, bool $manage = false): ClassroomCustomExercise
    {
        $this->authorizeClassCustom($classroom, $manage);
        abort_unless($cce->classroom_id === $classroom->id, 404);

        return $cce;
    }

    /** Mở bảng điểm của một bài tập (trong bài học) ở lớp — tạo cài đặt của lớp cho bài nếu chưa có. */
    public function customOpen(Classroom $classroom, CustomExercise $exercise): RedirectResponse
    {
        $this->authorizeClassCustom($classroom, false);
        abort_unless($exercise->course_id === $classroom->course_id && (! $exercise->source_classroom_id || $exercise->source_classroom_id === $classroom->id), 404);
        $cce = ClassroomCustomExercise::firstOrCreate(['classroom_id' => $classroom->id, 'custom_exercise_id' => $exercise->id], ['lesson_id' => $exercise->lesson_id]);

        return redirect()->route($this->classCustomPrefix().'.custom.results', [$classroom, $cce]);
    }

    public function customResults(Classroom $classroom, ClassroomCustomExercise $cce): View
    {
        $this->cceOf($classroom, $cce)->load('exercise.items', 'lesson');
        $attempts = $cce->attempts()->where('status', '!=', 'in_progress')->get()->groupBy('student_id');

        return view('custom.class.results', [
            'layout' => $this->classCustomLayout(), 'prefix' => $this->classCustomPrefix(), 'classroom' => $classroom, 'cce' => $cce,
            'students' => $classroom->students()->orderBy('last_name')->get(), 'attempts' => $attempts,
        ]);
    }

    public function customReview(Classroom $classroom, ClassroomCustomExercise $cce, CustomAttempt $attempt): View
    {
        $this->cceOf($classroom, $cce);
        abort_unless($attempt->classroom_custom_exercise_id === $cce->id, 404);

        return view('custom.class.review', [
            'layout' => $this->classCustomLayout(), 'prefix' => $this->classCustomPrefix(), 'classroom' => $classroom, 'cce' => $cce->load('exercise.items'),
            'attempt' => $attempt->load('student'),
        ]);
    }

    /** Giáo viên chấm phần chờ chấm (điểm 0–10 mỗi phần) và / hoặc sửa điểm cả bài + nhận xét. */
    public function customSaveReview(Request $request, Classroom $classroom, ClassroomCustomExercise $cce, CustomAttempt $attempt): RedirectResponse
    {
        $this->cceOf($classroom, $cce);
        abort_unless($attempt->classroom_custom_exercise_id === $cce->id, 404);
        $data = $request->validate([
            'items' => ['nullable', 'array'], 'items.*.score' => ['nullable', 'numeric', 'min:0', 'max:10'], 'items.*.comment' => ['nullable', 'string', 'max:3000'],
            'teacher_score' => ['nullable', 'numeric', 'min:0', 'max:10'], 'teacher_comment' => ['nullable', 'string', 'max:5000'],
        ]);
        $service = app(CustomExerciseService::class);
        foreach ($data['items'] ?? [] as $itemId => $row) {
            if (isset($row['score']) && $row['score'] !== '') {
                $service->gradeItem($attempt, (int) $itemId, (float) $row['score'] / 10, ['by' => 'teacher', 'comment' => $row['comment'] ?? null]);
                $attempt->refresh();
            }
        }
        $attempt->update(['teacher_score' => $data['teacher_score'] ?? null, 'teacher_comment' => $data['teacher_comment'] ?? null, 'reviewed_at' => now()]);

        return back()->with('status', 'Đã lưu điểm.');
    }

    public function customRecording(Classroom $classroom, ClassroomCustomExercise $cce, CustomAttempt $attempt, int $item): BinaryFileResponse
    {
        $this->cceOf($classroom, $cce);
        abort_unless($attempt->classroom_custom_exercise_id === $cce->id, 404);
        $path = ($attempt->files ?? [])[$item] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store']);
    }

    public function customUnassign(Classroom $classroom, ClassroomCustomExercise $cce): RedirectResponse
    {
        $this->cceOf($classroom, $cce, true);
        if ($cce->attempts()->exists()) {
            return back()->withErrors(['cce' => 'Bài đã có học viên làm, không gỡ được (kết quả sẽ mất). Có thể đặt hạn nộp để đóng bài.']);
        }
        $cce->delete();

        return back()->with('status', 'Đã gỡ bài khỏi lớp.');
    }

    /** Sửa giờ mở / hạn nộp / số lượt của bài trong lớp. */
    public function customUpdateAssignment(Request $request, Classroom $classroom, ClassroomCustomExercise $cce): RedirectResponse
    {
        $this->cceOf($classroom, $cce, true);
        $data = $request->validate([
            'opens_at' => ['nullable', 'date'], 'due_at' => ['nullable', 'date'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:50'], 'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'show_answers' => ['required', 'in:'.implode(',', array_keys(ClassroomCustomExercise::SHOW_ANSWERS))],
        ]);
        $cce->update($data);

        return back()->with('status', 'Đã lưu cài đặt bài.');
    }
}
