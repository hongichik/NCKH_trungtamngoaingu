<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ResultController extends Controller
{
    public function index(): View
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $registrations = $student->examRegistrations()
            ->with(['examSession', 'examLevel'])
            ->latest()
            ->get();

        $certificates = $student->certificates()->latest('issue_date')->get();

        return view('portal.results', [
            'student' => $student,
            'registrations' => $registrations,
            'certificates' => $certificates,
        ]);
    }
}
