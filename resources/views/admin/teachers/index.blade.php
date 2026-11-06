@extends('layouts.admin')

@section('title', 'Tài khoản giáo viên')

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        @if (auth()->user()->hasPermission('teachers', 'create'))
            <a href="{{ route('admin.teachers.create') }}" class="btn btn-primary">+ Thêm giáo viên</a>
        @endif
        <span class="text-muted small ms-auto">Giáo viên đăng nhập tại <a href="{{ route('teacher.login') }}" target="_blank">{{ route('teacher.login') }}</a></span>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>Họ tên</th><th>Email</th><th>Điện thoại</th><th class="text-end">Giáo trình</th><th>Trạng thái</th><th>Đăng nhập gần nhất</th><th class="w-1"></th></tr></thead>
                <tbody>
                    @forelse ($teachers as $t)
                        <tr>
                            <td class="fw-semibold">{{ $t->name }}</td>
                            <td class="text-muted">{{ $t->email }}</td>
                            <td class="text-muted">{{ $t->phone }}</td>
                            <td class="text-end">{{ $t->textbooks_count }}</td>
                            <td>{!! $t->is_active ? '<span class="badge bg-success-lt">Hoạt động</span>' : '<span class="badge bg-danger-lt">Đã khoá</span>' !!}</td>
                            <td class="text-muted small">{{ $t->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="text-nowrap">
                                @if (auth()->user()->hasPermission('teachers', 'edit'))
                                    <a href="{{ route('admin.teachers.edit', $t) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                                @endif
                                @if (auth()->user()->hasPermission('teachers', 'delete'))
                                    <form method="POST" action="{{ route('admin.teachers.destroy', $t) }}" class="d-inline" onsubmit="return confirm('Xoá tài khoản giáo viên này?')">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Xoá</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Chưa có giáo viên nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $teachers->links() }}</div>
@endsection
