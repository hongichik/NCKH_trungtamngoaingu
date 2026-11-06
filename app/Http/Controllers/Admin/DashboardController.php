<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Contact;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Post;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $stats = [
            [
                'key' => 'posts', 'label' => 'Bài viết', 'icon' => 'ti-article',
                'count' => Post::count(), 'route' => 'admin.posts.index', 'color' => 'primary',
            ],
            [
                'key' => 'students', 'label' => 'Học viên', 'icon' => 'ti-users',
                'count' => Student::count(), 'route' => 'admin.students.index', 'color' => 'azure',
            ],
            [
                'key' => 'certificates', 'label' => 'Chứng chỉ', 'icon' => 'ti-certificate',
                'count' => Certificate::count(), 'route' => 'admin.certificates.index', 'color' => 'green',
            ],
            [
                'key' => 'galleries', 'label' => 'Thư viện ảnh', 'icon' => 'ti-photo',
                'count' => Gallery::count(), 'route' => 'admin.galleries.index', 'color' => 'orange',
            ],
            [
                'key' => 'pages', 'label' => 'Trang tĩnh', 'icon' => 'ti-file-text',
                'count' => Page::count(), 'route' => 'admin.pages.index', 'color' => 'purple',
            ],
            [
                'key' => 'contacts', 'label' => 'Liên hệ chưa đọc', 'icon' => 'ti-mail',
                'count' => Contact::where('status', 'unread')->count(), 'route' => 'admin.contacts.index', 'color' => 'red',
            ],
            [
                'key' => 'exam_registrations', 'label' => 'Đăng ký thi HSK', 'icon' => 'ti-language',
                'count' => ExamRegistration::type('hsk')->where('status', '!=', 'cancelled')->count(),
                'route' => 'admin.exam-registrations.index', 'routeParams' => ['type' => 'hsk'], 'color' => 'indigo',
            ],
            [
                'key' => 'exam_registrations', 'label' => 'Đăng ký thi Tin học', 'icon' => 'ti-device-laptop',
                'count' => ExamRegistration::type('cntt')->where('status', '!=', 'cancelled')->count(),
                'route' => 'admin.exam-registrations.index', 'routeParams' => ['type' => 'cntt'], 'color' => 'teal',
            ],
        ];

        $stats = array_values(array_filter($stats, fn ($stat) => $user->hasAnyPermissionFor($stat['key'])));

        $postsChart = null;
        if ($user->hasAnyPermissionFor('posts')) {
            $months = collect(range(5, 0))->map(fn ($i) => Carbon::now()->subMonths($i)->startOfMonth());

            // Gom nhóm trong PHP để chạy được trên mọi CSDL (MySQL / SQLite / PostgreSQL).
            $counts = Post::where('created_at', '>=', $months->first())->pluck('created_at')
                ->countBy(fn ($d) => Carbon::parse($d)->format('Y-m'));

            $postsChart = [
                'labels' => $months->map(fn ($m) => $m->translatedFormat('m/Y'))->all(),
                'data' => $months->map(fn ($m) => (int) ($counts[$m->format('Y-m')] ?? 0))->all(),
            ];
        }

        $certificatesChart = null;
        if ($user->hasAnyPermissionFor('certificates')) {
            $currentYear = (int) date('Y');
            $startYear = $currentYear - 5;

            $counts = Certificate::whereBetween('issue_date', [Carbon::create($startYear)->startOfYear(), Carbon::create($currentYear)->endOfYear()])
                ->pluck('issue_date')->countBy(fn ($d) => Carbon::parse($d)->year);

            $years = range($startYear, $currentYear);

            $certificatesChart = [
                'labels' => array_map('strval', $years),
                'data' => array_map(fn ($y) => (int) ($counts[$y] ?? 0), $years),
            ];
        }

        $recentContacts = $user->hasAnyPermissionFor('contacts')
            ? Contact::latest()->take(5)->get()
            : collect();

        $examSummary = null;
        if ($user->hasAnyPermissionFor('exam_registrations') || $user->hasAnyPermissionFor('exams')) {
            $examSummary = [
                'pending_payment' => ExamRegistration::where('status', 'pending_payment')->count(),
                'revenue' => (int) ExamRegistration::where('status', 'paid')->sum('price'),
            ];
        }

        $upcomingSessions = $user->hasAnyPermissionFor('exams')
            ? ExamSession::where('status', 'open')
                ->whereDate('exam_date', '>=', now()->toDateString())
                ->withCount(['activeRegistrations as registrations_count'])
                ->orderBy('exam_date')
                ->take(6)
                ->get()
            : collect();

        return view('admin.dashboard', [
            'stats' => $stats,
            'postsChart' => $postsChart,
            'certificatesChart' => $certificatesChart,
            'recentContacts' => $recentContacts,
            'examSummary' => $examSummary,
            'upcomingSessions' => $upcomingSessions,
        ]);
    }
}
