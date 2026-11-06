<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ImportsWithAi;
use App\Http\Controllers\Concerns\ManagesCustomExercises;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CustomExercise;
use Illuminate\Support\Collection;

/** Tab "Bài tập tuỳ chỉnh" của khoá — quản trị viên xem, sửa, ẩn mọi bài, đánh dấu "bài chuẩn". */
class CustomExerciseController extends Controller
{
    use ImportsWithAi;
    use ManagesCustomExercises;

    protected function customLayout(): string
    {
        return 'layouts.admin';
    }

    protected function customRoutePrefix(): string
    {
        return 'admin.courses.custom';
    }

    protected function actor(): object
    {
        return auth()->user();
    }

    protected function canUseLibrary(Course $course): bool
    {
        return true;
    }

    protected function canManage(CustomExercise $exercise): bool
    {
        return true;
    }

    protected function assignableClassrooms(Course $course): Collection
    {
        return $course->classrooms()->orderBy('name')->get(['id', 'name', 'course_id']);
    }

    protected function isAdminSide(): bool
    {
        return true;
    }
}
