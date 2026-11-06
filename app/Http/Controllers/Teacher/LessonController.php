<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\EditsLessons;
use App\Http\Controllers\Controller;
use App\Models\Course;

/** Bài học & video của khoá — giáo viên được cấp quyền sửa khoá. */
class LessonController extends Controller
{
    use EditsLessons;

    protected function lessonLayout(): string
    {
        return 'layouts.teacher';
    }

    protected function lessonRoutePrefix(): string
    {
        return 'teacher.lessons';
    }

    protected function authorizeCourse(Course $course): void
    {
        abort_unless(auth()->user()->canEditCourse($course), 403, 'Bạn chưa được cấp quyền sửa khoá học này.');
    }
}
