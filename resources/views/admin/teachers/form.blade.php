@extends('layouts.admin')

@section('title', $teacher->exists ? 'Sửa giáo viên' : 'Thêm giáo viên')

@section('content')
    <form method="POST" action="{{ $teacher->exists ? route('admin.teachers.update', $teacher) : route('admin.teachers.store') }}" class="card" style="max-width: 640px">
        @csrf @if ($teacher->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="mb-3"><label class="form-label required">Họ tên</label><input name="name" class="form-control" value="{{ old('name', $teacher->name) }}" required></div>
            <div class="mb-3"><label class="form-label required">Email đăng nhập</label><input type="email" name="email" class="form-control" value="{{ old('email', $teacher->email) }}" required></div>
            <div class="mb-3"><label class="form-label">Điện thoại</label><input name="phone" class="form-control" value="{{ old('phone', $teacher->phone) }}"></div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label {{ $teacher->exists ? '' : 'required' }}">Mật khẩu {{ $teacher->exists ? '(để trống nếu không đổi)' : '' }}</label><input type="password" name="password" class="form-control" {{ $teacher->exists ? '' : 'required' }} autocomplete="new-password"></div>
                <div class="col-md-6"><label class="form-label">Nhập lại mật khẩu</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
            </div>
            <label class="form-check form-switch">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $teacher->is_active))>
                <span class="form-check-label">Cho phép đăng nhập</span>
            </label>
        </div>
        <div class="card-footer">
            <button class="btn btn-primary">Lưu</button>
            <a href="{{ route('admin.teachers.index') }}" class="btn btn-link">Huỷ</a>
        </div>
    </form>
@endsection
