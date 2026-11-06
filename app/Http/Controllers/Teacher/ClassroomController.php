<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\ImportsStudents;
use App\Http\Controllers\Concerns\ManagesClassCustom;
use App\Http\Controllers\Controller;
use App\Models\AccountLog;
use App\Models\Classroom;
use App\Models\CourseLesson;
use App\Models\Student;
use App\Models\TextbookUnit;
use App\Models\UnitTest;
use App\Support\Progress\Gamification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Lớp được gán cho giáo viên (V2: quản trị viên tạo lớp, đặt thời hạn, gán giáo viên).
 * Giáo viên chỉ thấy lớp mình được gán; thêm / bớt học viên khi lớp cho quyền "can_add_students".
 */
class ClassroomController extends Controller
{
    use ImportsStudents;
    use ManagesClassCustom;

    protected function classCustomLayout(): string
    {
        return 'layouts.teacher';
    }

    protected function classCustomPrefix(): string
    {
        return 'teacher.classrooms';
    }

    protected function authorizeClassCustom(Classroom $classroom, bool $manage): void
    {
        $this->own($classroom, $manage ? 'can_customize' : null);
    }

    protected function importLayout(): string
    {
        return 'layouts.teacher';
    }

    protected function importRoute(string $name, Classroom $classroom): string
    {
        return $name === 'back' ? route('teacher.classrooms.show', $classroom) : route('teacher.classrooms.import.'.$name, $classroom);
    }

    public function import(Request $request, Classroom $classroom): View
    {
        return $this->importPreview($request, $this->own($classroom, 'can_add_students'));
    }

    public function importSave(Request $request, Classroom $classroom): View
    {
        return $this->importCommit($request, $this->own($classroom, 'can_add_students'));
    }

    public function credentials(Classroom $classroom): Response
    {
        return $this->importCredentials($this->own($classroom, 'can_add_students'));
    }

    public function template(): Response
    {
        return $this->importTemplate();
    }

    public function searchStudents(Request $request, Classroom $classroom): JsonResponse
    {
        return $this->searchForClass($request, $this->own($classroom, 'can_add_students'));
    }

    public function addExisting(Request $request, Classroom $classroom): RedirectResponse
    {
        return $this->addExistingToClass($request, $this->own($classroom, 'can_add_students'));
    }

    public function resetPassword(Classroom $classroom, Student $student): RedirectResponse
    {
        return $this->resetStudentPassword($this->own($classroom, 'can_reset_password'), $student);
    }

    private function own(Classroom $classroom, ?string $perm = null): Classroom
    {
        abort_unless(auth()->user()->teaches($classroom), 404);
        if ($perm) {
            abort_unless(auth()->user()->hasClassPermission($classroom, $perm), 403, 'Bạn chưa được cấp quyền này ở lớp '.$classroom->name.'.');
        }

        return $classroom;
    }

    public function index(): View
    {
        return view('teacher.classrooms.index', [
            'classrooms' => auth()->user()->classrooms()->with('course')->withCount(['students', 'assignments'])->get(),
        ]);
    }

    public function show(Classroom $classroom): View
    {
        $this->own($classroom)->load(['course', 'students' => fn ($q) => $q->orderBy('last_name')]);

        return view('teacher.classrooms.show', [
            'classroom' => $classroom,
            'assignments' => $classroom->assignments()->with('unit')->get()->each(function ($a) {
                $a->submitted_count = $a->tests()->whereIn('status', ['completed', 'grading'])->distinct()->count('student_id');
            }),
            'perms' => $classroom->teachers()->whereKey(auth()->id())->first()->pivot,
        ]);
    }

    /** Lịch mở bài học của lớp. */
    public function lessonSchedule(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->own($classroom, 'can_customize');
        CourseLesson::saveSchedule($classroom, (array) $request->input('lessons', []));

        return back()->with('status', 'Đã lưu lịch mở bài học.');
    }

    /** Bản đồ năng lực lớp: học viên × Unit (điểm tổng cao nhất) + trung bình lớp theo Unit × kỹ năng. */
    public function competency(Classroom $classroom): View
    {
        $this->own($classroom)->load(['course', 'students' => fn ($q) => $q->orderBy('last_name')]);
        $tests = UnitTest::whereIn('assignment_id', $classroom->assignments()->where('kind', 'unit')->pluck('id'))
            ->where('status', 'completed')->get();
        $units = TextbookUnit::whereIn('id', $tests->pluck('unit_id')->unique())->orderBy('position')->get();

        $grid = [];   // student_id => unit_id => điểm cao nhất
        $skill = [];  // unit_id => skill => [điểm cao nhất của từng học viên]
        foreach ($tests->groupBy(fn ($t) => $t->student_id.':'.$t->unit_id) as $group) {
            $t = $group->first();
            $grid[$t->student_id][$t->unit_id] = $group->max('total_score');
            $best = [];
            foreach ($group as $x) {
                foreach ($x->skill_scores ?? [] as $sk => $sc) {
                    $best[$sk] = max($best[$sk] ?? 0, (float) $sc);
                }
            }
            foreach ($best as $sk => $sc) {
                $skill[$t->unit_id][$sk][] = $sc;
            }
        }
        $skillAvg = array_map(fn ($u) => array_map(fn ($v) => round(array_sum($v) / count($v), 1), $u), $skill);
        // + bài tập trong bài học (khoá học kiểu mới): cột "L{id}"
        $lessonMap = app(\App\Support\Progress\LessonCompetency::class)->forClassroom($classroom);
        $units = $units->concat($lessonMap['units']);
        foreach ($lessonMap['grid'] as $sid => $row) {
            $grid[$sid] = ($grid[$sid] ?? []) + $row;
        }
        $skillAvg += $lessonMap['skillAvg'];

        return view('teacher.classrooms.competency', [
            'classroom' => $classroom, 'units' => $units, 'grid' => $grid, 'skillAvg' => $skillAvg,
            'board' => app(Gamification::class)->leaderboard($classroom, 100),
        ]);
    }

    /** Giáo viên chỉ bật / tắt bảng xếp hạng; tên, khoá, thời hạn, link mời do quản trị viên chỉnh. */
    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->own($classroom)->update(['show_leaderboard' => $request->boolean('show_leaderboard')]);

        return back()->with('status', 'Đã lưu lớp học.');
    }

    /** Thêm học viên theo email (mỗi dòng / dấu phẩy 1 email) — học viên phải có tài khoản cổng thí sinh. */
    public function addStudents(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->own($classroom, 'can_add_students');
        $emails = collect(preg_split('/[\s,;]+/', (string) $request->validate(['emails' => ['required', 'string', 'max:20000']])['emails']))
            ->map(fn ($e) => mb_strtolower(trim($e)))->filter()->unique();

        $found = Student::whereIn(\DB::raw('LOWER(email)'), $emails->all())->get(['id', 'email']);
        $classroom->students()->syncWithoutDetaching($found->pluck('id')->all());
        $missing = $emails->diff($found->pluck('email')->map(fn ($e) => mb_strtolower($e)));

        return back()->with('status', 'Đã thêm '.$found->count().' học viên.'.($missing->isNotEmpty() ? ' Không tìm thấy tài khoản: '.$missing->take(10)->implode(', ').($missing->count() > 10 ? '…' : '').' (học viên chưa có tài khoản: gửi link mời của lớp để học viên đăng ký rồi vào lớp).' : ''));
    }

    public function removeStudent(Classroom $classroom, Student $student): RedirectResponse
    {
        $this->own($classroom, 'can_add_students')->students()->detach($student->id);
        AccountLog::record('remove_from_class', $student->id, $classroom->id);

        return back()->with('status', 'Đã xoá học viên khỏi lớp (kết quả bài làm vẫn được giữ).');
    }
}
