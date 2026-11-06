@extends('layouts.portal')

@section('title', 'Đổi mật khẩu')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <h1 class="h4 mb-3">Đổi mật khẩu</h1>
            @if ($forced)
                <div class="alert alert-warning">Tài khoản của bạn được tạo sẵn hoặc vừa được cấp lại mật khẩu. Hãy đặt mật khẩu mới để tiếp tục.</div>
            @endif
            <form method="POST" action="{{ route('portal.password.update') }}" class="card shadow-sm border-0">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu hiện tại {{ $forced ? '(mật khẩu được phát)' : '' }}</label>
                        <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required autocomplete="current-password">
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu mới (ít nhất 6 ký tự)</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="6" autocomplete="new-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Nhập lại mật khẩu mới</label>
                        <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                    </div>
                </div>
                <div class="card-footer bg-white d-flex">
                    @unless ($forced)<a href="{{ route('portal.home') }}" class="btn btn-link">Huỷ</a>@endunless
                    <button class="btn btn-primary ms-auto">Lưu mật khẩu</button>
                </div>
            </form>
        </div>
    </div>
@endsection
