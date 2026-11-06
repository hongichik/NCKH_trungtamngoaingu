<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EditsLessons;
use App\Http\Controllers\Controller;
use App\Models\Course;

/** Bài học & video của khoá — trang quản trị (quyền "Khoá học & lớp học · Sửa"). */
class LessonController extends Controller
{
    use EditsLessons;

    protected function lessonLayout(): string
    {
        return 'layouts.admin';
    }

    protected function lessonRoutePrefix(): string
    {
        return 'admin.courses.lessons';
    }

    protected function authorizeCourse(Course $course): void
    {
        // Quyền đã kiểm tra bằng middleware permission:learning,edit.
    }
}
