<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ImportsStudents;
use App\Http\Controllers\Concerns\ManagesClassCustom;
use App\Http\Controllers\Controller;
use App\Models\AccountLog;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lớp học do quản trị viên tạo: thuộc một khoá, có ngày giờ bắt đầu / kết thúc, gán giáo viên với quyền riêng.
 * "Cho vào lớp sau khi kết thúc" + số ngày gia hạn: chỉ chủ hệ thống (users.id = 1) đổi được.
 */
class ClassroomController extends Controller
{
    use ImportsStudents;
    use ManagesClassCustom;

    protected function classCustomLayout(): string
    {
        return 'layouts.admin';
    }

    protected function classCustomPrefix(): string
    {
        return 'admin.classrooms';
    }

    protected function authorizeClassCustom(Classroom $classroom, bool $manage): void
    {
        // Quyền đã kiểm tra bằng middleware permission:learning,…
    }

    protected function importLayout(): string
    {
        return 'layouts.admin';
    }

    protected function importRoute(string $name, Classroom $classroom): string
    {
        return $name === 'back' ? route('admin.classrooms.show', $classroom) : route('admin.classrooms.import.'.$name, $classroom);
    }

    public function index(Request $request): View
    {
        $classrooms = Classroom::with('course:id,title', 'teachers:id,name')->withCount('students')
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->when($request->filled('status'), fn ($q) => $q->status($request->status))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.classrooms.index', ['classrooms' => $classrooms, 'courses' => Course::orderBy('title')->get(['id', 'title'])]);
    }

    public function create(Request $request): View
    {
        return $this->form(new Classroom(['course_id' => $request->integer('course_id') ?: null, 'allow_join' => false]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $classroom = Classroom::create($data + ['created_by' => auth()->id()]);
        $this->syncTeachers($request, $classroom);

        return redirect()->route('admin.classrooms.show', $classroom)->with('status', 'Đã tạo lớp. Thêm học viên ở bên phải.');
    }

    public function show(Classroom $classroom): View
    {
        $classroom->load(['course', 'teachers', 'students' => fn ($q) => $q->orderBy('last_name')]);

        return view('admin.classrooms.show', [
            'classroom' => $classroom,
            'logs' => AccountLog::where('classroom_id', $classroom->id)->with('student:id,last_name,first_name,email')->latest('id')->limit(30)->get(),
        ]);
    }

    public function edit(Classroom $classroom): View
    {
        return $this->form($classroom->load('teachers'));
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $classroom->update($this->validated($request, $classroom));
        $this->syncTeachers($request, $classroom);

        return redirect()->route('admin.classrooms.show', $classroom)->with('status', 'Đã lưu lớp học.');
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $classroom->delete();

        return redirect()->route('admin.classrooms.index')->with('status', 'Đã xoá lớp.');
    }

    // ---------------------------------------------------------------- học viên

    public function import(Request $request, Classroom $classroom): View
    {
        return $this->importPreview($request, $classroom);
    }

    public function importSave(Request $request, Classroom $classroom): View
    {
        return $this->importCommit($request, $classroom);
    }

    public function credentials(Classroom $classroom): Response
    {
        return $this->importCredentials($classroom);
    }

    public function template(): Response
    {
        return $this->importTemplate();
    }

    public function searchStudents(Request $request, Classroom $classroom): JsonResponse
    {
        return $this->searchForClass($request, $classroom);
    }

    public function addExisting(Request $request, Classroom $classroom): RedirectResponse
    {
        return $this->addExistingToClass($request, $classroom);
    }

    public function resetPassword(Classroom $classroom, Student $student): RedirectResponse
    {
        return $this->resetStudentPassword($classroom, $student);
    }

    public function removeStudent(Classroom $classroom, Student $student): RedirectResponse
    {
        $classroom->students()->detach($student->id);
        AccountLog::record('remove_from_class', $student->id, $classroom->id);

        return back()->with('status', 'Đã xoá học viên khỏi lớp (kết quả bài làm vẫn được giữ).');
    }

    /** Lịch mở bài học của lớp. */
    public function lessonSchedule(Request $request, Classroom $classroom): RedirectResponse
    {
        CourseLesson::saveSchedule($classroom, (array) $request->input('lessons', []));

        return back()->with('status', 'Đã lưu lịch mở bài học.');
    }

    // ---------------------------------------------------------------- nội bộ

    private function form(Classroom $classroom): View
    {
        return view('admin.classrooms.form', [
            'classroom' => $classroom,
            'courses' => Course::orderBy('title')->get(['id', 'title', 'language']),
            'teachers' => Teacher::orderBy('name')->get(['id', 'name', 'email', 'is_active']),
            'isOwner' => auth()->user()->isOwner(),
        ]);
    }

    private function validated(Request $request, ?Classroom $classroom = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'course_id' => ['required', Rule::exists('courses', 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'allow_join' => ['nullable', 'boolean'],
            'new_link' => ['nullable', 'boolean'],
            'show_leaderboard' => ['nullable', 'boolean'],
            'allow_after_end' => ['nullable', 'boolean'],
            'grace_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'teachers' => ['nullable', 'array'],
            'teachers.*.id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
        ], ['ends_at.after' => 'Giờ kết thúc phải sau giờ bắt đầu.']);

        $out = [
            'name' => $data['name'], 'course_id' => $data['course_id'], 'description' => $data['description'] ?? null,
            'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null,
            'allow_join' => $request->boolean('allow_join'), 'show_leaderboard' => $request->boolean('show_leaderboard'),
        ];
        if ($request->boolean('new_link')) {
            $out['invite_token'] = Str::random(32);
        }
        // Chỉ chủ hệ thống bật / tắt "cho vào sau khi kết thúc"; người khác gửi lên cũng bị bỏ qua.
        if (auth()->user()->isOwner()) {
            $out['allow_after_end'] = $request->boolean('allow_after_end');
            $out['grace_days'] = $out['allow_after_end'] ? ($data['grace_days'] ?? null) : null;
        }

        return $out;
    }

    /** teachers[n][id], teachers[n][can_customize|can_add_students|can_reset_password] */
    private function syncTeachers(Request $request, Classroom $classroom): void
    {
        $sync = [];
        foreach ((array) $request->input('teachers', []) as $row) {
            if (empty($row['id'])) {
                continue;
            }
            $sync[(int) $row['id']] = collect(array_keys(Classroom::PERMS))->mapWithKeys(fn ($p) => [$p => ! empty($row[$p])])->all();
        }
        $classroom->teachers()->sync($sync);
        // "Giáo viên chính" = người đầu tiên (hiển thị cho học viên).
        $classroom->forceFill(['teacher_id' => array_key_first($sync)])->saveQuietly();
    }
}
