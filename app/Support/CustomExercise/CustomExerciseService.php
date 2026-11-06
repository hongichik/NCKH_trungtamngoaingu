<?php

namespace App\Support\CustomExercise;

use App\Models\AiJob;
use App\Models\ClassroomCustomExercise;
use App\Models\Course;
use App\Models\CustomAttempt;
use App\Models\CustomExercise;
use App\Models\Student;
use App\Support\Progress\Gamification;
use App\Support\QuestionTypes\QuestionType;
use App\Support\QuestionTypes\Registry;
use Illuminate\Support\Collection;

/**
 * Nghiệp vụ bài tập tuỳ chỉnh: dạng bài dùng được cho khoá, bắt đầu / nộp / chấm bài làm, chấm phần Viết / Nói (AI hoặc giáo viên).
 */
class CustomExerciseService
{
    /** Dạng bài đang bật, hợp ngôn ngữ của khoá. @return Collection<string, QuestionType> */
    public function typesFor(Course $course): Collection
    {
        return collect(Registry::all())
            ->filter(fn (QuestionType $t) => Registry::enabled($t->code) && in_array($t->lang, ['any', $course->language], true) && $t->standard !== 'v1')
            ->sortBy(fn (QuestionType $t) => array_search($t->standard, array_keys(QuestionType::STANDARDS)).'-'.$t->name);
    }

    /** @return list<string> lỗi nội dung của một phần */
    public function validateItem(string $typeCode, mixed $content): array
    {
        $type = Registry::find($typeCode);
        if (! $type) {
            return ['Dạng bài không tồn tại: '.$typeCode];
        }
        if (! is_array($content)) {
            return ['Nội dung phải là JSON object.'];
        }

        return $type->engine()->validate($content, $type);
    }

    // ---------------------------------------------------------------- làm bài

    /** @return array{0: ?CustomAttempt, 1: ?string} */
    public function start(ClassroomCustomExercise $a, Student $student): array
    {
        $a->loadMissing('classroom', 'exercise', 'lesson.videos');
        if (! $a->classroom->students()->whereKey($student->id)->exists()) {
            return [null, 'Bạn không thuộc lớp được giao bài này.'];
        }
        if (! $a->classroom->accessOpen()) {
            return [null, $a->classroom->closedReason()];
        }
        if ($a->opens_at && $a->opens_at->isFuture()) {
            return [null, 'Bài chưa mở. Mở lúc '.$a->opens_at->format('H:i d/m/Y').'.'];
        }
        if ($a->isClosed()) {
            return [null, 'Đã quá hạn nộp bài.'];
        }
        if ($a->lesson && $a->lesson->require_video && ! $a->lesson->videosCompletedBy($student->id)) {
            return [null, 'Hãy xem xong video của bài học “'.$a->lesson->title.'” trước khi làm bài này.'];
        }
        $current = $a->attempts()->where('student_id', $student->id)->where('status', 'in_progress')->latest('id')->first();
        if ($current) {
            if ($current->deadline_at && $current->deadline_at->copy()->addMinutes(2)->isPast()) {
                $this->submit($current, $current->answers ?? []);   // hết giờ → tự nộp phần đã lưu
            } else {
                return [$current, null];
            }
        }
        $used = $a->attempts()->where('student_id', $student->id)->count();
        if ($a->max_attempts && $used >= $a->max_attempts) {
            return [null, 'Bạn đã dùng hết '.$a->max_attempts.' lượt làm bài này.'];
        }
        $limit = $a->timeLimit();
        $deadline = $limit ? now()->addMinutes($limit) : null;
        if ($a->due_at && $deadline && $deadline->gt($a->due_at)) {
            $deadline = $a->due_at->copy();
        }

        return [CustomAttempt::create(['classroom_custom_exercise_id' => $a->id, 'student_id' => $student->id, 'status' => 'in_progress',
            'started_at' => now(), 'deadline_at' => $deadline]), null];
    }

    /**
     * Chấm bài làm. $answers = [item_id => object câu trả lời theo dạng bài].
     * Nộp trễ quá 2 phút so với giờ hết hạn → bài tính 0 điểm (như bài giao V1).
     */
    public function submit(CustomAttempt $attempt, array $answers): CustomAttempt
    {
        $a = $attempt->assignment()->with('exercise.items')->first();
        $late = $attempt->deadline_at && $attempt->deadline_at->copy()->addMinutes(2)->isPast();
        $exercise = $a->exercise;
        $result = [];
        $pending = false;
        foreach ($exercise->items as $item) {
            $type = $item->type();
            if (! $type) {
                continue;
            }
            $answer = $late ? [] : (array) ($answers[$item->id] ?? []);
            if ($type->engine === 'speaking' && ! empty(($attempt->files ?? [])[$item->id])) {
                $answer['recorded'] = true;
            }
            $graded = Registry::grade($type, $item->content, $answer, array_merge($exercise->scoring ?? [], $item->scoring ?? []));
            $pending = $pending || $graded['pending'];
            $result[$item->id] = ['type' => $type->code, 'title' => $item->title ?: $type->name, 'parts' => $graded['parts'], 'score' => $graded['score'], 'pending' => $graded['pending']];
        }
        $attempt->fill(['answers' => $answers, 'result' => $result, 'submitted_at' => now(), 'status' => $pending ? 'grading' : 'graded']);
        $this->recalc($attempt);
        $attempt->save();

        if ($pending) {
            if (filled(config('ai.openai.key'))) {
                AiJob::dispatch('custom_grade', ['attempt_id' => $attempt->id], null, 0, 'student:'.$attempt->student_id);
            }
        } else {
            $this->finished($attempt);
        }

        return $attempt;
    }

    /**
     * Chấm lại các bài đã nộp của một bài tập theo nội dung hiện tại. Giữ nguyên phần Viết / Nói đã chấm (AI / giáo viên),
     * không cộng phần mới thêm vào lượt cũ, bỏ phần đã xoá. Điểm giáo viên sửa tay (teacher_score) vẫn được ưu tiên như trước.
     *
     * @return int số bài làm đã chấm lại
     */
    public function regradeExercise(CustomExercise $exercise): int
    {
        $exercise->load('items');
        $items = $exercise->items->keyBy('id');
        $n = 0;
        $attempts = CustomAttempt::whereIn('classroom_custom_exercise_id', $exercise->assignments()->pluck('id'))->whereIn('status', ['graded', 'grading'])->get();
        foreach ($attempts as $attempt) {
            $result = [];
            foreach ($attempt->result ?? [] as $itemId => $old) {
                $item = $items[$itemId] ?? null;
                $type = $item?->type();
                if (! $item || ! $type) {
                    continue;   // phần đã xoá khỏi bài
                }
                if (in_array($type->engine, ['writing', 'speaking'], true) && empty($old['pending'])) {
                    $result[$itemId] = $old;   // đã được AI / giáo viên chấm
                    continue;
                }
                $answer = (array) (($attempt->answers ?? [])[$itemId] ?? []);
                if ($type->engine === 'speaking' && ! empty(($attempt->files ?? [])[$itemId])) {
                    $answer['recorded'] = true;
                }
                $graded = Registry::grade($type, $item->content, $answer, array_merge($exercise->scoring ?? [], $item->scoring ?? []));
                $result[$itemId] = ['type' => $type->code, 'title' => $item->title ?: $type->name, 'parts' => $graded['parts'], 'score' => $graded['score'],
                    'pending' => $graded['pending']] + array_intersect_key($old, ['feedback' => 1]);
            }
            $attempt->result = $result;
            $this->recalc($attempt);
            $attempt->save();
            $n++;
        }

        return $n;
    }

    /** Tính lại tổng điểm từ kết quả từng phần. */
    public function recalc(CustomAttempt $attempt): void
    {
        $earned = 0.0;
        $max = 0.0;
        foreach ($attempt->result ?? [] as $r) {
            $earned += (float) ($r['score']['earned'] ?? 0);
            $max += (float) ($r['score']['max'] ?? 0);
        }
        $attempt->earned = round($earned, 2);
        $attempt->max = round($max, 2);
        $attempt->score = $max > 0 ? round($earned / $max * 10, 1) : 0;
        if ($attempt->status !== 'in_progress' && ! collect($attempt->result ?? [])->contains(fn ($r) => ! empty($r['pending']))) {
            $attempt->status = 'graded';
        }
    }

    /** Gán điểm cho một phần chờ chấm (AI hoặc giáo viên): $fraction 0–1 của điểm tối đa phần đó. */
    public function gradeItem(CustomAttempt $attempt, int $itemId, float $fraction, array $feedback = []): void
    {
        $result = $attempt->result ?? [];
        if (! isset($result[$itemId])) {
            return;
        }
        $fraction = max(0, min(1, $fraction));
        $max = (float) $result[$itemId]['score']['max'];
        $result[$itemId]['score']['earned'] = round($max * $fraction, 2);
        $result[$itemId]['score']['percent'] = round($fraction * 100, 1);
        $result[$itemId]['score']['ten'] = round($fraction * 10, 1);
        $result[$itemId]['score']['pending'] = false;
        $result[$itemId]['score']['scaled'] = ($feedback['band'] ?? null) ? 'Band '.$feedback['band'] : number_format($fraction * 10, 1).' / 10';
        $result[$itemId]['pending'] = false;
        $result[$itemId]['feedback'] = $feedback;
        $wasGrading = $attempt->status === 'grading';
        $attempt->result = $result;
        $this->recalc($attempt);
        $attempt->save();
        if ($wasGrading && $attempt->status === 'graded') {
            $this->finished($attempt);
        }
    }

    /** Chấm xong → cộng XP như bài giao. */
    private function finished(CustomAttempt $attempt): void
    {
        $student = $attempt->student;
        if ($student) {
            app(Gamification::class)->award($student, 'test', 10 + (int) round((float) $attempt->score * 2), ['custom_attempt' => $attempt->id]);
        }
    }
}
