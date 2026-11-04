<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Quản trị') - {{ config('app.name') }}</title>
    <link href="{{ asset('vendor/tabler/css/tabler.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/tabler-icons/tabler-icons.min.css') }}" rel="stylesheet">
    <style>
        :root {
            --tblr-primary: #0a4a75;
        }
        .navbar-brand-image { height: 2rem; }
        .page-body .table thead th { white-space: nowrap; }
        .navbar-vertical .navbar-nav .nav-link.active {
            background: rgba(255, 255, 255, .08);
            border-radius: .375rem;
        }
    </style>
    @stack('styles')
</head>
<body>
<div class="page">
    <aside class="navbar navbar-vertical navbar-expand-lg navbar-dark" data-bs-theme="dark">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <h1 class="navbar-brand navbar-brand-autodark">
                <a href="{{ route('admin.dashboard') }}">{{ config('app.name') }}</a>
            </h1>
            <div class="navbar-nav flex-row d-lg-none">
                <div class="nav-item d-flex">
                    <a href="{{ route('home') }}" class="nav-link px-2" title="Xem website" target="_blank">
                        <i class="ti ti-external-link"></i>
                    </a>
                </div>
            </div>
            <div class="collapse navbar-collapse" id="sidebar-menu">
                <ul class="navbar-nav pt-lg-3">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <span class="nav-link-icon"><i class="ti ti-layout-dashboard"></i></span>
                            <span class="nav-link-title">Tổng quan</span>
                        </a>
                    </li>
                    @php
                        $user = auth()->user();
                        $inContentGroup = request()->routeIs(['admin.posts.*', 'admin.categories.*', 'admin.pages.*', 'admin.galleries.*', 'admin.menus.*']);
                        $inStudentsGroup = request()->routeIs(['admin.students.*', 'admin.certificates.*']);
                        $inHskGroup = request()->routeIs(['admin.exams.*', 'admin.exam-registrations.*']) && request('type', 'hsk') !== 'cntt';
                        $inCnttGroup = request()->routeIs(['admin.exams.*', 'admin.exam-registrations.*']) && request('type') === 'cntt';
                        $showContentGroup = $user->hasAnyPermissionFor('posts') || $user->hasAnyPermissionFor('categories') || $user->hasAnyPermissionFor('pages') || $user->hasAnyPermissionFor('galleries') || $user->hasAnyPermissionFor('menus');
                        $showStudentsGroup = $user->hasAnyPermissionFor('students') || $user->hasAnyPermissionFor('certificates');
                        $showExamGroups = $user->hasAnyPermissionFor('exams') || $user->hasAnyPermissionFor('exam_registrations');
                    @endphp

                    @if ($showContentGroup)
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ $inContentGroup ? 'active' : '' }}" href="#nav-content" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ $inContentGroup ? 'true' : 'false' }}">
                                <span class="nav-link-icon"><i class="ti ti-notes"></i></span>
                                <span class="nav-link-title">Nội dung</span>
                            </a>
                            <div class="dropdown-menu {{ $inContentGroup ? 'show' : '' }}">
                                @if ($user->hasAnyPermissionFor('posts'))
                                    <a class="dropdown-item {{ request()->routeIs('admin.posts.*') ? 'active' : '' }}" href="{{ route('admin.posts.index') }}">
                                        <i class="ti ti-article me-2"></i>Bài viết
                                    </a>
                                @endif
                                @if ($user->hasAnyPermissionFor('categories'))
                                    <a class="dropdown-item {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">
                                        <i class="ti ti-category-2 me-2"></i>Danh mục
                                    </a>
                                @endif
                                @if ($user->hasAnyPermissionFor('pages'))
                                    <a class="dropdown-item {{ request()->routeIs('admin.pages.*') ? 'active' : '' }}" href="{{ route('admin.pages.index') }}">
                                        <i class="ti ti-file-text me-2"></i>Trang tĩnh
                                    </a>
                                @endif
                                @if ($user->hasAnyPermissionFor('galleries'))
                                    <a class="dropdown-item {{ request()->routeIs('admin.galleries.*') ? 'active' : '' }}" href="{{ route('admin.galleries.index') }}">
                                        <i class="ti ti-photo me-2"></i>Thư viện ảnh
                                    </a>
                                @endif
                                @if ($user->hasAnyPermissionFor('menus'))
                                    <a class="dropdown-item {{ request()->routeIs('admin.menus.*') ? 'active' : '' }}" href="{{ route('admin.menus.index') }}">
                                        <i class="ti ti-menu-2 me-2"></i>Menu website
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endif

                    @if ($user->hasAnyPermissionFor('contacts'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.contacts.*') ? 'active' : '' }}" href="{{ route('admin.contacts.index') }}">
                                <span class="nav-link-icon"><i class="ti ti-mail"></i></span>
                                <span class="nav-link-title">Liên hệ</span>
                            </a>
                        </li>
                    @endif

                    @if ($showStudentsGroup)
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ $inStudentsGroup ? 'active' : '' }}" href="#nav-students" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ $inStudentsGroup ? 'true' : 'false' }}">
                                <span class="nav-link-icon"><i class="ti ti-school"></i></span>
                                <span class="nav-link-title">Học viên & Chứng chỉ</span>
                            </a>
                            <div class="dropdown-menu {{ $inStudentsGroup ? 'show' : '' }}">
                                @if ($user->hasAnyPermissionFor('students'))
                                    <a class="dropdown-item {{ request()->routeIs('admin.students.*') ? 'active' : '' }}" href="{{ route('admin.students.index') }}">
                                        <i class="ti ti-users me-2"></i>Học viên
                                    </a>
                                @endif
                                @if ($user->hasAnyPermissionFor('certificates'))
                                    <a class="dropdown-item {{ request()->routeIs('admin.certificates.*') ? 'active' : '' }}" href="{{ route('admin.certificates.index') }}">
                                        <i class="ti ti-certificate me-2"></i>Chứng chỉ
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endif

                    @if ($showExamGroups)
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ $inHskGroup ? 'active' : '' }}" href="#nav-hsk" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ $inHskGroup ? 'true' : 'false' }}">
                                <span class="nav-link-icon"><i class="ti ti-language"></i></span>
                                <span class="nav-link-title">Kỳ thi HSK</span>
                            </a>
                            <div class="dropdown-menu {{ $inHskGroup ? 'show' : '' }}">
                                @if ($user->hasAnyPermissionFor('exams'))
                                    <a class="dropdown-item {{ $inHskGroup && request()->routeIs('admin.exams.*') ? 'active' : '' }}" href="{{ route('admin.exams.index', ['type' => 'hsk']) }}">
                                        <i class="ti ti-calendar-event me-2"></i>Đợt thi & Lệ phí
                                    </a>
                                @endif
                                @if ($user->hasAnyPermissionFor('exam_registrations'))
                                    <a class="dropdown-item {{ $inHskGroup && request()->routeIs('admin.exam-registrations.*') ? 'active' : '' }}" href="{{ route('admin.exam-registrations.index', ['type' => 'hsk']) }}">
                                        <i class="ti ti-clipboard-list me-2"></i>Đăng ký dự thi
                                    </a>
                                @endif
                            </div>
                        </li>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ $inCnttGroup ? 'active' : '' }}" href="#nav-cntt" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ $inCnttGroup ? 'true' : 'false' }}">
                                <span class="nav-link-icon"><i class="ti ti-device-laptop"></i></span>
                                <span class="nav-link-title">Kỳ thi Tin học</span>
                            </a>
                            <div class="dropdown-menu {{ $inCnttGroup ? 'show' : '' }}">
                                @if ($user->hasAnyPermissionFor('exams'))
                                    <a class="dropdown-item {{ $inCnttGroup && request()->routeIs('admin.exams.*') ? 'active' : '' }}" href="{{ route('admin.exams.index', ['type' => 'cntt']) }}">
                                        <i class="ti ti-calendar-event me-2"></i>Đợt thi & Lệ phí
                                    </a>
                                @endif
                                @if ($user->hasAnyPermissionFor('exam_registrations'))
                                    <a class="dropdown-item {{ $inCnttGroup && request()->routeIs('admin.exam-registrations.*') ? 'active' : '' }}" href="{{ route('admin.exam-registrations.index', ['type' => 'cntt']) }}">
                                        <i class="ti ti-clipboard-list me-2"></i>Đăng ký dự thi
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endif

                    @if ($user->is_admin)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                                <span class="nav-link-icon"><i class="ti ti-shield-lock"></i></span>
                                <span class="nav-link-title">Người dùng &amp; phân quyền</span>
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </aside>

    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <h2 class="page-title">@yield('title')</h2>
                    </div>
                    <div class="col-auto ms-auto d-print-none">
                        <div class="dropdown">
                            <a href="#" class="btn btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <span class="avatar avatar-sm bg-primary-lt me-2"><i class="ti ti-user"></i></span>
                                {{ auth()->user()->name }}
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a href="{{ route('home') }}" class="dropdown-item" target="_blank"><i class="ti ti-external-link me-2"></i>Xem website</a>
                                <div class="dropdown-divider"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger"><i class="ti ti-logout me-2"></i>Đăng xuất</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible" role="alert">
                        {{ session('status') }}
                        <a class="btn-close" data-bs-dismiss="alert"></a>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <a class="btn-close" data-bs-dismiss="alert"></a>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>

        <footer class="footer footer-transparent d-print-none">
            <div class="container-xl">
                <div class="row text-center align-items-center flex-row-reverse">
                    <div class="col-lg-auto ms-lg-auto">
                        <ul class="list-inline list-inline-dots mb-0">
                            <li class="list-inline-item text-muted">Trang quản trị {{ config('app.name') }}</li>
                        </ul>
                    </div>
                    <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                        <ul class="list-inline list-inline-dots mb-0">
                            <li class="list-inline-item text-muted">&copy; {{ date('Y') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>
<script src="{{ asset('vendor/popper/popper.min.js') }}"></script>
<script src="{{ asset('vendor/tabler/js/tabler.min.js') }}"></script>
@stack('scripts')
</body>
</html>
