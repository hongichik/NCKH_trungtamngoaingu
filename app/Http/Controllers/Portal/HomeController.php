<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\ClassroomCustomExercise;
use App\Models\CourseLesson;
use App\Models\CustomAttempt;
use App\Models\ExamSession;
use App\Models\Student;
use App\Models\UnitTest;
use App\Support\Progress\Gamification;
use App\Support\Vocab\Flashcards;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Trang chủ cổng thí sinh: hồ sơ, đăng ký thi, lớp học (nếu có), chứng chỉ. */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $classes = $student->classrooms()->with('teacher:id,name')->get();
        $todo = $classes->isEmpty() ? collect() : Assignment::whereIn('classroom_id', $classes->pluck('id'))
            ->where(fn ($q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>', now()))
            ->whereDoesntHave('tests', fn ($q) => $q->where('student_id', $student->id)->whereIn('status', ['completed', 'grading']))
            ->with('classroom:id,name')->orderByRaw('due_at is null')->orderBy('due_at')->limit(5)->get();

        $lessonTodo = $this->lessonTodo($student, $classes);

        $openSessions = ExamSession::openForRegistration()->orderBy('exam_date')->get()->groupBy('exam_type');

        $reviewed = UnitTest::where('student_id', $student->id)->whereNotNull('assignment_id')
            ->whereHas('attempts', fn ($a) => $a->where('reviewed_at', '>=', now()->subDays(14))->where(fn ($w) => $w->whereNotNull('teacher_score')->orWhereNotNull('teacher_comment')))
            ->with('assignment:id,title,classroom_id')->latest('updated_at')->limit(5)->get();

        $game = app(Gamification::class);
        $stat = $classes->isEmpty() ? null : $game->stats($student);

        return view('portal.home', [
            'stat' => $stat,
            'streak' => $stat ? $game->currentStreak($stat) : 0,
            'todayXp' => $stat ? $game->todayXp($student) : 0,
            'reviewed' => $reviewed,
            'vocabDue' => $classes->isEmpty() ? 0 : app(Flashcards::class)->dueCount($student),
            'student' => $student,
            'profileDone' => $student->hasCompletedCoreProfile(),
            'registrations' => $student->examRegistrations()->with(['examSession', 'examLevel'])->latest()->limit(5)->get(),
            'certificateCount' => $student->certificates()->count(),
            'classes' => $classes,
            'todo' => $todo,
            'lessonTodo' => $lessonTodo,
            'openSessions' => $openSessions,
        ]);
    }

    /**
     * Bài tập trong bài học (khoá học → bài học → bài tập) học viên chưa làm xong: bài học đang mở với lớp,
     * bài tập đã xuất bản, chưa có lượt đã nộp. Xếp theo thứ tự bài học; bài đang làm dở lên trước.
     *
     * @return \Illuminate\Support\Collection<int, array{classroom: \App\Models\Classroom, lesson: \App\Models\CourseLesson, exercise: \App\Models\CustomExercise, in_progress: bool}>
     */
    private function lessonTodo(Student $student, $classes)
    {
        $out = collect();
        foreach ($classes as $class) {
            if (! $class->course_id) {
                continue;
            }
            $attempts = CustomAttempt::where('student_id', $student->id)
                ->whereIn('classroom_custom_exercise_id', ClassroomCustomExercise::where('classroom_id', $class->id)->pluck('id'))
                ->with('assignment:id,custom_exercise_id')->get(['id', 'classroom_custom_exercise_id', 'status']);
            $doneIds = $attempts->whereIn('status', ['graded', 'grading'])->pluck('assignment.custom_exercise_id')->all();
            $doingIds = $attempts->where('status', 'in_progress')->pluck('assignment.custom_exercise_id')->all();
            $lessons = CourseLesson::where('course_id', $class->course_id)->where('is_published', true)->orderBy('position')->orderBy('id')->get();
            foreach ($lessons as $lesson) {
                if (! $lesson->openFor($class)) {
                    continue;
                }
                foreach ($lesson->exercisesFor($class)->get(['id', 'title', 'lesson_id']) as $ex) {
                    if (! in_array($ex->id, $doneIds, true)) {
                        $out->push(['classroom' => $class, 'lesson' => $lesson, 'exercise' => $ex, 'in_progress' => in_array($ex->id, $doingIds, true)]);
                    }
                }
            }
        }

        return $out->sortByDesc('in_progress')->values();
    }
}
