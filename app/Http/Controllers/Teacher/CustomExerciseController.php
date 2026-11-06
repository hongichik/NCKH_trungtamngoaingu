<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\ImportsWithAi;
use App\Http\Controllers\Concerns\ManagesCustomExercises;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CustomExercise;
use Illuminate\Support\Collection;

/**
 * Thư viện bài tập tuỳ chỉnh của khoá ở cổng giáo viên.
 * Vào được khi: được sửa khoá, hoặc có quyền "tuỳ chỉnh bài tập" ở ít nhất một lớp của khoá.
 * Sửa được: bài mình tạo; người được sửa khoá sửa được mọi bài. Bài khác thì nhân bản.
 */
class CustomExerciseController extends Controller
{
    use ImportsWithAi;
    use ManagesCustomExercises;

    protected function customLayout(): string
    {
        return 'layouts.teacher';
    }

    protected function customRoutePrefix(): string
    {
        return 'teacher.custom';
    }

    protected function actor(): object
    {
        return auth()->user();
    }

    protected function canUseLibrary(Course $course): bool
    {
        return auth()->user()->canEditCourse($course) || $this->assignableClassrooms($course)->isNotEmpty();
    }

    protected function canManage(CustomExercise $exercise): bool
    {
        return $exercise->isCreator(auth()->user()) || auth()->user()->canEditCourse($exercise->course);
    }

    protected function assignableClassrooms(Course $course): Collection
    {
        return auth()->user()->classrooms()->where('classrooms.course_id', $course->id)->wherePivot('can_customize', true)->get(['classrooms.id', 'classrooms.name', 'classrooms.course_id']);
    }

    protected function isAdminSide(): bool
    {
        return false;
    }
}
