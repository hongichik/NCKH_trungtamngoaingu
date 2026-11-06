@extends('layouts.app')

@section('title', 'Đăng ký / Đăng nhập thi sinh')

@section('content')
    @php
        // Có ?type= (từ nút đăng ký thi): mặc định tab Đăng ký. Không có (học viên lớp học, người đã có tài khoản): mặc định tab Đăng nhập.
        $loginFailed = $errors->any() && is_null(old('full_name'));
        $showLogin = $loginFailed || (! $type && ! old('full_name'));
    @endphp
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="text-center mb-4">
                    @if ($type)
                        <h1 class="h3 mb-2">Đăng ký chứng chỉ {{ $type === 'hsk' ? 'HSK' : 'Ứng dụng CNTT' }}</h1>
                        <p class="text-muted mb-0">Đăng ký tài khoản thí sinh mới, hoặc đăng nhập nếu bạn đã có tài khoản.</p>
                    @else
                        <h1 class="h3 mb-2">Cổng học viên &amp; thí sinh</h1>
                        <p class="text-muted mb-0">Đăng nhập để vào lớp học, làm bài tập, xem điểm; hoặc đăng ký tài khoản mới để đăng ký thi.</p>
                    @endif
                </div>

                <div class="card shadow-soft border-0">
                    <div class="card-body p-4">
                        <ul class="nav nav-pills nav-fill mb-4" id="authTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $showLogin ? '' : 'active' }}" id="register-tab" data-bs-toggle="pill" data-bs-target="#register-pane" type="button">Đăng ký tài khoản</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $showLogin ? 'active' : '' }}" id="login-tab" data-bs-toggle="pill" data-bs-target="#login-pane" type="button">Đã có tài khoản</button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade {{ $showLogin ? '' : 'show active' }}" id="register-pane">
                                <form method="POST" action="{{ route('portal.register') }}">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $type }}">

                                    <div class="mb-3">
                                        <label class="form-label">Họ và tên</label>
                                        <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name') }}" required>
                                        @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Số điện thoại</label>
                                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" required>
                                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <label class="form-label">Mật khẩu</label>
                                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-6 mb-3">
                                            <label class="form-label">Nhập lại mật khẩu</label>
                                            <input type="password" name="password_confirmation" class="form-control" required>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary w-100">Đăng ký</button>
                                </form>
                            </div>

                            <div class="tab-pane fade {{ $showLogin ? 'show active' : '' }}" id="login-pane">
                                <form method="POST" action="{{ route('portal.login') }}">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $type }}">

                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Mật khẩu</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <button class="btn btn-primary w-100">Đăng nhập</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
