<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        <div class="d-flex align-items-center gap-2 min-width-0">
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
                <button class="btn btn-sm btn-outline-light">Đăng xuất</button>
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
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('portal.profile.*') ? 'active' : '' }}" href="{{ route('portal.profile.edit') }}">
                            <i class="bi bi-person-vcard"></i> Thông tin thí sinh
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
    @stack('scripts')
</body>
</html>
