<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Khoá học giáo viên được quản trị viên cấp quyền sửa (bài học, bài tập, video). */
class CourseController extends Controller
{
    /** "Khoá học tôi được sửa". */
    public function index(): View
    {
        return view('teacher.courses.index', [
            'courses' => auth()->user()->editableCourses()->withCount(['classrooms', 'lessons'])->orderBy('title')->get(),
        ]);
    }
}
