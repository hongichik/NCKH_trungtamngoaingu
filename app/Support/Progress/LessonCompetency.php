<?php

namespace App\Support\Progress;

use App\Models\Classroom;
use App\Models\ClassroomCustomExercise;
use App\Models\CustomAttempt;
use App\Models\Student;
use App\Support\QuestionTypes\Registry;
use Illuminate\Support\Collection;

/**
 * Bản đồ năng lực cho khoá học kiểu mới (Khoá học → Bài học → Bài tập): đọc kết quả các bài làm đã chấm
 * (custom_attempts.result), quy về thang 10 theo từng kỹ năng của dạng bài. Dùng cùng bản đồ theo Unit (giáo trình cũ).
 */
class LessonCompetency
{
    /** Cột kỹ năng: 4 kỹ năng + từ vựng / ngữ pháp / phát âm gộp chung. */
    public const SKILLS = ['reading' => 'Đọc', 'listening' => 'Nghe', 'speaking' => 'Nói', 'writing' => 'Viết', 'vocab' => 'Từ vựng & NP'];

    public static function skillOf(string $typeCode): string
    {
        $sk = Registry::find($typeCode)?->skill;

        return in_array($sk, ['reading', 'listening', 'speaking', 'writing'], true) ? $sk : 'vocab';
    }

    /** Điểm thang 10 theo kỹ năng của một bài làm (bỏ phần đang chờ chấm). @return array<string, float> */
    public static function attemptSkills(CustomAttempt $a): array
    {
        $by = [];
        foreach ($a->result ?? [] as $r) {
            if (! empty($r['pending']) || ! isset($r['score']['percent'], $r['type'])) {
                continue;
            }
            $by[self::skillOf($r['type'])][] = (float) $r['score']['percent'] / 10;
        }

        return array_map(fn ($v) => round(array_sum($v) / count($v), 1), $by);
    }

    /** @return Collection<int, CustomAttempt> bài làm đã chấm, kèm bài học */
    private function attempts(array $cceIds, ?int $studentId = null): Collection
    {
        return CustomAttempt::whereIn('classroom_custom_exercise_id', $cceIds)->where('status', 'graded')
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->with('assignment.exercise.lesson:id,title,position,course_id')->get()
            ->filter(fn ($a) => $a->assignment?->exercise?->lesson);
    }

    /** Hàng bản đồ của học viên: mỗi bài học — điểm cao nhất, số lần, điểm cao nhất từng kỹ năng. */
    public function forStudent(Student $student): Collection
    {
        $cce = ClassroomCustomExercise::whereIn('classroom_id', $student->classrooms()->pluck('classrooms.id'))->pluck('id')->all();

        return $this->attempts($cce, $student->id)->groupBy(fn ($a) => $a->assignment->exercise->lesson_id)->map(function ($group) {
            $skills = [];
            foreach ($group as $a) {
                foreach (self::attemptSkills($a) as $sk => $v) {
                    $skills[$sk] = max($skills[$sk] ?? 0, $v);
                }
            }
            $lesson = $group->first()->assignment->exercise->lesson;

            return ['unit' => $lesson, 'best' => (float) $group->max('score'), 'count' => $group->count(), 'skills' => $skills,
                'last' => $group->max('submitted_at'), 'sort' => [$lesson->course_id, $lesson->position]];
        })->sortBy('sort')->values();
    }

    /**
     * Bản đồ lớp: cột = bài học (id dạng "L{id}" để không trùng id Unit), lưới học viên × bài học, trung bình lớp bài học × kỹ năng.
     *
     * @return array{units: Collection, grid: array, skillAvg: array}
     */
    public function forClassroom(Classroom $classroom): array
    {
        $attempts = $this->attempts(ClassroomCustomExercise::where('classroom_id', $classroom->id)->pluck('id')->all());
        $units = $attempts->map(fn ($a) => $a->assignment->exercise->lesson)->unique('id')->sortBy('position')
            ->map(fn ($l) => (object) ['id' => 'L'.$l->id, 'title' => $l->title])->values();
        $grid = [];
        $skill = [];
        foreach ($attempts->groupBy(fn ($a) => $a->student_id.':'.$a->assignment->exercise->lesson_id) as $group) {
            $a = $group->first();
            $key = 'L'.$a->assignment->exercise->lesson_id;
            $grid[$a->student_id][$key] = (float) $group->max('score');
            $best = [];
            foreach ($group as $x) {
                foreach (self::attemptSkills($x) as $sk => $v) {
                    $best[$sk] = max($best[$sk] ?? 0, $v);
                }
            }
            foreach ($best as $sk => $v) {
                $skill[$key][$sk][] = $v;
            }
        }

        return ['units' => $units, 'grid' => $grid, 'skillAvg' => array_map(fn ($u) => array_map(fn ($v) => round(array_sum($v) / count($v), 1), $u), $skill)];
    }
}
