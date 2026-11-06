<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Cổng thí sinh') - {{ config('app.name') }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --brand: #0a4a75;
            --brand-dark: #073657;
            --accent: #ef5823;
            --ink: #101828;
        }
        body {
            font-family: 'Be Vietnam Pro', -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f5f7fa;
            color: var(--ink);
        }
        .portal-topbar {
            background: var(--brand);
            color: #fff;
        }
        .portal-topbar .brand-mark { font-weight: 700; }
        .portal-topbar .student-name { font-weight: 600; }
        .portal-sidebar-toggle {
            border-color: rgba(255,255,255,.5);
            color: #fff;
        }
        .portal-sidebar {
            background: #fff;
            border-right: 1px solid rgba(16,24,40,.08);
            width: 260px;
            flex-shrink: 0;
        }
        @media (min-width: 992px) {
            .portal-sidebar { min-width: 260px; }
        }
        .portal-sidebar .nav-link {
            color: var(--ink);
            border-radius: .6rem;
            padding: .65rem .9rem;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: .6rem;
            line-height: 1.3;
        }
        .portal-sidebar .nav-link i {
            flex-shrink: 0;
            width: 1.2rem;
            text-align: center;
            margin-top: .15rem;
        }
        .portal-sidebar .nav-link.active {
            background: rgba(10,74,117,.08);
            color: var(--brand);
            font-weight: 600;
        }
        .portal-sidebar .nav-link:hover { background: rgba(10,74,117,.06); }
        .badge-status-pending { background: #f59f00 !important; }
        .badge-status-paid { background: #2fb344 !important; }
        .badge-status-cancelled { background: #6c757d !important; }
        .hsk-block {
            border: 1px dashed var(--accent);
            border-radius: .8rem;
            background: rgba(239,88,35,.04);
        }

        @media (max-width: 991.98px) {
            .portal-sidebar { min-height: 100vh; }
        }
        @media (max-width: 575.98px) {
            .portal-topbar .student-name { display: none; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="portal-topbar d-flex align-items-center justify-content-between px-3 py-2 gap-2">
        <div class="d-flex align-items-center gap-2" style="min-width: 0">
            <button class="btn btn-sm portal-sidebar-toggle border d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#portalSidebar" aria-label="Menu">
                <i class="bi bi-list fs-5"></i>
            </button>
            <a href="{{ route('home') }}" class="text-white text-decoration-none brand-mark text-truncate">
                <i class="bi bi-mortarboard-fill me-1"></i>{{ config('app.name') }}
            </a>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <span class="student-name small text-truncate" style="max-width: 160px;">{{ auth('student')->user()->full_name ?: auth('student')->user()->email }}</span>
            <form method="POST" action="{{ route('portal.logout') }}" class="mb-0">
                @csrf
                <button class="btn btn-sm btn-outline-light text-nowrap" title="Đăng xuất"><i class="bi bi-box-arrow-right"></i><span class="d-none d-sm-inline"> Đăng xuất</span></button>
            </form>
        </div>
    </div>

    <div class="d-flex">
        <nav id="portalSidebar" class="portal-sidebar offcanvas-lg offcanvas-start p-3">
            <div class="offcanvas-header d-lg-none">
                <h5 class="offcanvas-title">Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#portalSidebar"></button>
            </div>
            <div class="offcanvas-body d-block p-0">
                <ul class="nav nav-pills flex-column gap-1">
                    @php
                        $me = auth('student')->user();
                        $myClassIds = $me->classrooms()->pluck('classrooms.id');
                        $todo = $myClassIds->isEmpty() ? 0 : \App\Models\Assignment::whereIn('classroom_id', $myClassIds)
                            ->where(fn ($q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
                            ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>', now()))
                            ->whereDoesntHave('tests', fn ($q) => $q->where('student_id', $me->id)->whereIn('status', ['completed', 'grading']))
                            ->count();
                    @endphp
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('portal.home') ? 'active' : '' }}" href="{{ route('portal.home') }}">
                            <i class="bi bi-house"></i> Trang chủ
                        </a>
                    </li>
                    @if ($myClassIds->isNotEmpty())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('portal.classes.*', 'portal.learning.*', 'portal.assignments.*') ? 'active' : '' }}" href="{{ route('portal.classes.index') }}">
                                <i class="bi bi-people"></i> Lớp học của tôi
                                @if ($todo)<span class="badge text-bg-danger ms-1">{{ $todo }}</span>@endif
                            </a>
                        </li>
                        @php $cards = app(\App\Support\Vocab\Flashcards::class); $hasVocab = $cards->decks($me)->isNotEmpty(); $vocabDue = $hasVocab ? $cards->dueCount($me) : 0; @endphp
                        {{-- Chỉ hiện khi lớp có từ vựng để ôn (giáo trình theo Unit) — tránh trang trống --}}
                        @if ($hasVocab || request()->routeIs('portal.vocab.*'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('portal.vocab.*') ? 'active' : '' }}" href="{{ route('portal.vocab.index') }}">
                                <i class="bi bi-card-heading"></i> Ôn từ vựng
                                @if ($vocabDue)<span class="badge text-bg-warning ms-1">{{ $vocabDue }}</span>@endif
                            </a>
                        </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('portal.mistakes.*', 'portal.practice.*') ? 'active' : '' }}" href="{{ route('portal.mistakes.index') }}">
                                <i class="bi bi-journal-x"></i> Sổ lỗi sai
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('portal.progress.*') ? 'active' : '' }}" href="{{ route('portal.progress.index') }}">
                                <i class="bi bi-graph-up-arrow"></i> Tiến độ & huy hiệu
                            </a>
                        </li>
                    @endif
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('portal.profile.*') ? 'active' : '' }}" href="{{ route('portal.profile.edit') }}">
                            <i class="bi bi-person-vcard"></i> Thông tin thí sinh
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('portal.password.*') ? 'active' : '' }}" href="{{ route('portal.password.edit') }}">
                            <i class="bi bi-key"></i> Đổi mật khẩu
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('portal.exam.*') && request()->route('type') === 'hsk' ? 'active' : '' }}" href="{{ route('portal.exam.show', ['type' => 'hsk']) }}">
                            <i class="bi bi-translate"></i> Đăng ký thi HSK
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('portal.exam.*') && request()->route('type') === 'cntt' ? 'active' : '' }}" href="{{ route('portal.exam.show', ['type' => 'cntt']) }}">
                            <i class="bi bi-laptop"></i> Đăng ký thi Tin học
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('portal.results.*') ? 'active' : '' }}" href="{{ route('portal.results.index') }}">
                            <i class="bi bi-clipboard-data"></i> Kết quả & Đăng ký của tôi
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="flex-grow-1 p-3 p-md-4" style="min-width: 0;">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if (session('status_error'))
                <div class="alert alert-danger">{{ session('status_error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="app-toasts" style="z-index: 1080"></div>
    <script>
    // Thông báo nhỏ: +XP, huy hiệu mới (dùng chung các trang học)
    window.appReward = function (r) {
        if (!r) return;
        const msgs = [];
        if (r.xp) msgs.push('<i class="bi bi-lightning-charge-fill text-warning"></i> +' + r.xp + ' XP');
        (r.badges || []).forEach((b) => msgs.push('<i class="bi bi-award-fill text-danger"></i> Huy hiệu mới: <strong>' + String(b).replace(/[<>&]/g, '') + '</strong>'));
        msgs.forEach((m) => {
            const el = document.createElement('div');
            el.className = 'toast align-items-center border-0 shadow';
            el.innerHTML = '<div class="d-flex"><div class="toast-body">' + m + '</div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>';
            document.getElementById('app-toasts').appendChild(el);
            new bootstrap.Toast(el, { delay: 2500 }).show();
            el.addEventListener('hidden.bs.toast', () => el.remove());
        });
    };
    </script>
    @stack('scripts')
</body>
</html>
