@extends('layouts.admin')

@section('title', $user->exists ? 'Sửa tài khoản' : 'Thêm tài khoản')

@section('content')
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Thông tin tài khoản</h3>

                        <div class="mb-3">
                            <label class="form-label">Họ tên</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mật khẩu {{ $user->exists ? '(để trống nếu không đổi)' : '' }}</label>
                            <input type="password" name="password" class="form-control" {{ $user->exists ? '' : 'required' }}>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nhập lại mật khẩu</label>
                            <input type="password" name="password_confirmation" class="form-control" {{ $user->exists ? '' : 'required' }}>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Phân quyền theo từng mục</h3>
                        <p class="text-muted small">Chọn quyền xem/thêm/sửa/xoá cho từng mục quản trị. Tài khoản không có quyền "Xem" ở mục nào sẽ không truy cập được mục đó.</p>

                        <div class="table-responsive">
                            <table class="table table-vcenter">
                                <thead>
                                    <tr>
                                        <th>Mục</th>
                                        @foreach ($actionLabels as $actionKey => $actionLabel)
                                            <th class="text-center">{{ $actionLabel }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($modules as $moduleKey => $module)
                                        <tr>
                                            <td class="fw-semibold">{{ $module['label'] }}</td>
                                            @foreach ($actionLabels as $actionKey => $actionLabel)
                                                <td class="text-center">
                                                    @php
                                                        $permission = $permissions->first(fn ($p) => $p->module === $moduleKey && $p->action === $actionKey);
                                                    @endphp
                                                    @if ($permission)
                                                        <input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $permission->id }}"
                                                            @checked(in_array($permission->id, old('permissions', $selectedPermissionIds)))>
                                                    @else
                                                        <span class="text-muted">&mdash;</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button class="btn btn-primary">Lưu</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-link">Huỷ</a>
        </div>
    </form>
@endsection
