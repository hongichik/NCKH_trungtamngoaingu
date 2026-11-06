<?php

namespace App\Support\Exercise;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseSet;
use App\Models\Student;
use App\Models\TextbookUnit;
use App\Models\UnitTest;
use Illuminate\Support\Facades\Cache;

/**
 * Bài làm 4 kỹ năng của 1 Unit: các phần Đọc / Nghe / Nói / Viết trên cùng 1 trang (chuyển qua lại tự do),
 * dùng bộ bài tập ĐÃ XUẤT BẢN mới nhất của từng kỹ năng; nộp 1 lần cho cả bài.
 * Đọc / Nghe chấm ngay; Nói / Viết chấm nền → khi đủ điểm mọi phần thì tính điểm tổng + xếp loại.
 */
class UnitTestService
{
    public const ORDER = ['reading', 'listening', 'speaking', 'writing'];

    /** Các phần của bài làm Unit này (kỹ năng nào chưa có bộ đã xuất bản thì bỏ qua). */
    public function sections(TextbookUnit $unit): array
    {
        $sections = [];
        foreach (self::ORDER as $skill) {
            $set = $unit->exerciseSets()->where('skill', $skill)->where('status', 'published')->whereHas('questions')->latest('published_at')->latest('id')->first();
            if ($set) {
                $sections[] = ['skill' => $skill, 'set_id' => $set->id, 'attempt_id' => null];
            }
        }

        return $sections;
    }

    public function start(Course $course, TextbookUnit $unit, Student $student): ?UnitTest
    {
        $sections = $this->sections($unit);
        if (! $sections) {
            return null;
        }
        // Lần đang làm dở trước đó → bỏ dở
        UnitTest::where('student_id', $student->id)->where('unit_id', $unit->id)->where('status', 'in_progress')->update(['status' => 'abandoned']);

        return UnitTest::create(['course_id' => $course->id, 'unit_id' => $unit->id, 'student_id' => $student->id, 'sections' => $sections, 'current' => 0, 'status' => 'in_progress']);
    }

    /** Thời gian châm chước sau khi hết giờ / hạn nộp (mạng chậm, tự nộp trễ vài giây). */
    public const GRACE_SECONDS = 180;

    /**
     * Bắt đầu làm bài được giao. Trả về [bài làm|null, lỗi|null].
     *
     * @return array{0: ?UnitTest, 1: ?string}
     */
    public function startAssignment(Assignment $a, Student $student): array
    {
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
        $existing = UnitTest::where('assignment_id', $a->id)->where('student_id', $student->id)->where('status', 'in_progress')->latest('id')->first();
        if ($existing) {
            return [$existing, null]; // đang làm dở → làm tiếp (không tính thêm lượt)
        }
        if ($a->max_attempts && $a->attemptsUsed($student->id) >= $a->max_attempts) {
            return [null, 'Bạn đã dùng hết '.$a->max_attempts.' lượt làm bài này.'];
        }

        $deadline = $a->time_limit_minutes ? now()->addMinutes($a->time_limit_minutes) : null;
        if ($a->due_at && (! $deadline || $a->due_at->lt($deadline))) {
            $deadline = $a->due_at; // không vượt quá hạn nộp
        }

        return [UnitTest::create([
            'course_id' => $a->classroom->course_id,
            'unit_id' => $a->unit_id,
            'student_id' => $student->id,
            'assignment_id' => $a->id,
            'sections' => array_map(fn ($s) => $s + ['attempt_id' => null], $a->sections),
            'current' => 0,
            'status' => 'in_progress',
            'deadline_at' => $deadline,
        ]), null];
    }

    /** Nộp sau giờ quá lâu (đã trừ thời gian châm chước) → bài làm không được tính. */
    public function isLate(UnitTest $test): bool
    {
        return $test->deadline_at && now()->gt($test->deadline_at->copy()->addSeconds(self::GRACE_SECONDS));
    }

    /**
     * Nộp cả bài: gắn lượt làm của từng phần vào bài làm → chờ chấm (Nói / Viết chấm nền) → tính điểm tổng.
     *
     * @param  array<int, ExerciseAttempt>  $attempts  chỉ số phần => lượt làm
     */
    public function submitAll(UnitTest $test, array $attempts): void
    {
        $sections = $test->sections;
        foreach ($attempts as $i => $attempt) {
            $sections[$i]['attempt_id'] = $attempt->id;
            $attempt->update(['unit_test_id' => $test->id]);
        }
        $test->update(['sections' => $sections, 'current' => max(0, count($sections) - 1), 'status' => 'grading']);
        $this->refresh($test);
    }

    /** Đủ điểm mọi phần → tính điểm tổng + xếp loại. Gọi lại sau khi chấm nền xong. */
    public function refresh(UnitTest $test): void
    {
        Cache::lock("unit-test:{$test->id}", 30)->block(15, function () use ($test) {
            $test->refresh();
            if (! in_array($test->status, ['grading', 'completed'], true)) {
                return;
            }
            $attempts = ExerciseAttempt::whereIn('id', array_filter(array_column($test->sections, 'attempt_id')))->get()->keyBy('id');
            $scores = [];
            foreach ($test->sections as $s) {
                $a = $attempts[$s['attempt_id']] ?? null;
                if (! $a || $a->status === 'grading') {
                    return; // còn phần đang chấm
                }
                $scores[$s['skill']][] = $a->status === 'graded' ? $a->finalScore() : 0.0;
            }
            // Đề thi thử có thể có nhiều phần cùng kỹ năng → lấy trung bình theo kỹ năng
            $scores = array_map(fn ($list) => round(array_sum($list) / count($list), 1), $scores);
            $total = round(array_sum($scores) / max(1, count($scores)), 1);
            $firstTime = $test->status !== 'completed';
            $test->update(['skill_scores' => $scores, 'total_score' => $total, 'level' => UnitTest::levelFor($total), 'status' => 'completed', 'finished_at' => $test->finished_at ?? now()]);
            if ($firstTime) {
                try {
                    app(\App\Support\Progress\Gamification::class)->testCompleted($test);
                } catch (\Throwable $e) {
                    report($e); // không để lỗi tính XP làm hỏng việc chấm bài
                }
            }
        });
    }

    public function refreshForAttempt(ExerciseAttempt $attempt): void
    {
        if ($attempt->unit_test_id && ($test = UnitTest::find($attempt->unit_test_id))) {
            $this->refresh($test);
        }
    }
}
