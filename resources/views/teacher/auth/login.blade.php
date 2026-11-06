<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập giáo viên - {{ config('app.name') }}</title>
    <link href="{{ asset('vendor/tabler/css/tabler.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/tabler-icons/tabler-icons.min.css') }}" rel="stylesheet">
    <style>:root { --tblr-primary: #0f766e; --tblr-primary-rgb: 15, 118, 110; }</style>
</head>
<body class="d-flex flex-column" style="background: linear-gradient(160deg, #ecfdf5, #f0f9ff)">
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <span class="avatar avatar-lg bg-teal text-white mb-2"><i class="ti ti-school fs-1"></i></span>
            <h2 class="mb-0">{{ config('app.name') }}</h2>
            <div class="text-muted">Cổng giáo viên</div>
        </div>
        <form class="card card-md" method="POST" action="{{ route('teacher.login.submit') }}">
            @csrf
            <div class="card-body">
                <h2 class="h3 text-center mb-4">Đăng nhập</h2>
                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mật khẩu</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <label class="form-check mb-3">
                    <input type="checkbox" name="remember" value="1" class="form-check-input">
                    <span class="form-check-label">Ghi nhớ đăng nhập</span>
                </label>
                <button class="btn btn-primary w-100">Đăng nhập</button>
            </div>
        </form>
        <div class="text-center text-muted mt-3 small">Chưa có tài khoản? Liên hệ quản trị viên của trung tâm.</div>
    </div>
</div>
</body>
</html>
