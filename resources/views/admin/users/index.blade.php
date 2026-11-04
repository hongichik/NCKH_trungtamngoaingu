@extends('layouts.admin')

@section('title', 'Người dùng & phân quyền')

@section('content')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary mb-3">+ Thêm tài khoản</a>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th>Quyền</th>
                        <th>Ngày tạo</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td class="text-muted">{{ $user->email }}</td>
                            <td>
                                @if ($user->is_admin)
                                    <span class="badge bg-primary text-white">Admin cao nhất</span>
                                @else
                                    <span class="badge bg-secondary-lt">Tài khoản thường</span>
                                @endif
                            </td>
                            <td>
                                @if ($user->is_admin)
                                    <span class="text-muted small">Toàn quyền</span>
                                @else
                                    <span class="badge bg-blue-lt">{{ $user->permissions_count }} quyền</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $user->created_at->format('d/m/Y') }}</td>
                            <td class="text-nowrap">
                                @unless ($user->is_admin)
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">Sửa quyền</a>
                                    @if ($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline" onsubmit="return confirm('Xoá tài khoản này?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Xoá</button>
                                        </form>
                                    @endif
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $users->links() }}</div>
@endsection
