<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Giáo viên') - {{ config('app.name') }}</title>
    <link href="{{ asset('vendor/tabler/css/tabler.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/tabler-icons/tabler-icons.min.css') }}" rel="stylesheet">
    <style>
        :root { --tblr-primary: #0f766e; --tblr-primary-rgb: 15, 118, 110; }
        .teacher-sidebar { background: #0f5f59 !important; }
        .teacher-sidebar .navbar-collapse { flex-direction: column; align-items: stretch; }
        .teacher-sidebar .nav-link.active { background: rgba(255, 255, 255, .12); border-radius: .375rem; }
        .teacher-brand { font-weight: 700; letter-spacing: .2px; line-height: 1.2; }
        .table thead th { white-space: nowrap; }
        .text-pre { white-space: pre-wrap; }
    </style>
    @stack('styles')
</head>
<body>
<div class="page">
    {{-- Menu dọc bên trái (giống trang admin) — quy tắc chung cho cổng giáo viên & học viên --}}
    <aside class="navbar navbar-vertical navbar-expand-lg teacher-sidebar" data-bs-theme="dark">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#teacher-menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <h1 class="navbar-brand navbar-brand-autodark">
                <a href="{{ route('teacher.dashboard') }}" class="teacher-brand text-white text-decoration-none">
                    <i class="ti ti-school me-1"></i>{{ config('app.name') }}
                    <div class="small fw-normal opacity-75">Cổng giáo viên</div>
                </a>
            </h1>
            <div class="collapse navbar-collapse" id="teacher-menu">
                <ul class="navbar-nav pt-lg-3">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}" href="{{ route('teacher.dashboard') }}">
                            <span class="nav-link-icon"><i class="ti ti-home"></i></span>
                            <span class="nav-link-title">Trang chủ</span>
                        </a>
                    </li>


                    <li class="nav-item mt-2"><span class="nav-link disabled text-uppercase small opacity-50 py-1">Lớp học</span></li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('teacher.classrooms.*', 'teacher.assignments.*', 'teacher.class-custom.*') ? 'active' : '' }}" href="{{ route('teacher.classrooms.index') }}">
                            <span class="nav-link-icon"><i class="ti ti-users-group"></i></span>
                            <span class="nav-link-title">Lớp của tôi</span>
                        </a>
                    </li>
                    @if (auth('teacher')->user()->editableCourses()->exists())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('teacher.courses.*', 'teacher.lessons.*', 'teacher.custom.*', 'teacher.ai-import.*', 'teacher.units.*') ? 'active' : '' }}" href="{{ route('teacher.courses.index') }}">
                                <span class="nav-link-icon"><i class="ti ti-school"></i></span>
                                <span class="nav-link-title">Khoá học tôi được sửa</span>
                            </a>
                        </li>
                    @endif

                </ul>

                <div class="mt-auto p-3 d-none d-lg-block border-top border-white border-opacity-10">
                    <div class="d-flex align-items-center">
                        <span class="avatar avatar-sm bg-white text-teal me-2"><i class="ti ti-user"></i></span>
                        <div class="flex-fill text-truncate">
                            <div class="text-white small fw-semibold text-truncate">{{ auth('teacher')->user()->name }}</div>
                            <div class="text-white small opacity-50 text-truncate">{{ auth('teacher')->user()->email }}</div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('teacher.logout') }}" class="mt-2">@csrf
                        <button class="btn btn-sm btn-outline-light w-100"><i class="ti ti-logout me-1"></i>Đăng xuất</button>
                    </form>
                </div>
                <form method="POST" action="{{ route('teacher.logout') }}" class="d-lg-none p-2">@csrf
                    <button class="btn btn-sm btn-outline-light w-100"><i class="ti ti-logout me-1"></i>Đăng xuất ({{ auth('teacher')->user()->name }})</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        @hasSection('pretitle')<div class="page-pretitle">@yield('pretitle')</div>@endif
                        <h2 class="page-title">@yield('title')</h2>
                    </div>
                    <div class="col-auto ms-auto">@yield('actions')</div>
                </div>
            </div>
        </div>
        <div class="page-body">
            <div class="container-xl">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible" role="alert">{{ session('status') }}<a class="btn-close" data-bs-dismiss="alert"></a></div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        <a class="btn-close" data-bs-dismiss="alert"></a>
                    </div>
                @endif
                @yield('content')
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('vendor/tabler/js/tabler.min.js') }}"></script>
@stack('scripts')
</body>
</html>
