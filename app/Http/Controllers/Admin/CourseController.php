<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiImport;
use App\Models\AiJob;
use App\Models\Course;
use App\Models\Teacher;
use App\Support\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Khoá học do quản trị viên tạo (V2); cấp quyền sửa khoá cho giáo viên. */
class CourseController extends Controller
{
    public function index(Request $request): View
    {
        $courses = Course::with('editors:id,name')->withCount(['classrooms', 'lessons'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->q.'%'))
            ->when($request->filled('language'), fn ($q) => $q->where('language', $request->language))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.courses.index', ['courses' => $courses]);
    }

    public function create(): View
    {
        return $this->form(new Course(['language' => 'en', 'is_published' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $course = Course::create($this->validated($request) + ['created_by' => auth()->id()]);
        $this->saveExtras($request, $course);

        return redirect()->route('admin.courses.lessons.index', $course)
            ->with('status', 'Đã tạo khoá. Thêm từng bài học; trong mỗi bài tải file bài tập cho AI dựng (hoặc để AI tự tạo, soạn tay) và thêm video.');
    }

    public function edit(Course $course): View
    {
        return $this->form($course);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $course->update($this->validated($request, $course));
        $this->saveExtras($request, $course);

        return back()->with('status', 'Đã lưu khoá học.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        // Cho phép xoá cả khi khoá đã có lớp: lớp được giữ lại (course_id → null, theo khoá ngoại),
        // bài học/bài tập/AI import của khoá bị xoá theo cascade. Huỷ trước các việc AI đang chờ của khoá.
        $course->classrooms()->update(['course_id' => null]);
        foreach (AiImport::where('course_id', $course->id)->get() as $import) {
            AiJob::where('batch', $import->batch())->where('status', AiJob::PENDING)->delete();
        }
        if ($course->cover_image) {
            Storage::disk('public')->delete($course->cover_image);
        }
        $course->delete();

        return redirect()->route('admin.courses.index')->with('status', 'Đã xoá khoá học.');
    }

    private function form(Course $course): View
    {
        return view('admin.courses.form', [
            'course' => $course->loadMissing('editors'),
            'teachers' => Teacher::orderBy('name')->get(['id', 'name', 'email', 'is_active']),
        ]);
    }

    private function validated(Request $request, ?Course $course = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'language' => ['required', Rule::in(['en', 'zh'])],
            'level' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_published' => ['nullable', 'boolean'],
            'show_reading_preview' => ['nullable', 'boolean'],
            'cover' => ['nullable', 'image', 'max:5120'],
            'editors' => ['nullable', 'array'],
            'editors.*' => ['integer', Rule::exists('teachers', 'id')],
        ]);
        unset($data['cover'], $data['editors']);
        $data['is_published'] = $request->boolean('is_published');
        $data['show_reading_preview'] = $request->boolean('show_reading_preview');

        return $data;
    }

    private function saveExtras(Request $request, Course $course): void
    {
        $course->editors()->sync($request->input('editors', []));
        if ($request->hasFile('cover')) {
            if ($course->cover_image) {
                Storage::disk('public')->delete($course->cover_image);
            }
            $course->update(['cover_image' => ImageOptimizer::store($request->file('cover'), 'courses', 1200, 800)]);
        }
    }
}
