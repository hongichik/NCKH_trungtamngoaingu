<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AiUsage;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $teacher = auth()->user();

        return view('teacher.dashboard', [
            'courses' => $teacher->editableCourses()->withCount('lessons')->orderBy('title')->limit(8)->get(),
            'classCount' => $teacher->classrooms()->count(),
            'aiToday' => AiUsage::where('actor_type', 'teacher')->where('actor_id', $teacher->id)->where('created_at', '>=', now()->startOfDay())->count(),
            'aiLimit' => (int) config('ai.limits.per_actor_daily'),
        ]);
    }
}
