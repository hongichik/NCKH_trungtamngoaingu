@extends('layouts.admin')

@section('title', $classroom->name)

@section('content')
    @php $canEdit = auth()->user()->hasPermission('learning', 'edit'); @endphp
    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <span class="text-muted">Khoá: <a href="{{ route('admin.courses.edit', $classroom->course_id) }}">{{ $classroom->course?->title }}</a> · Mã lớp {{ $classroom->code }}</span>
        @if ($canEdit)<a href="{{ route('admin.classrooms.edit', $classroom) }}" class="btn btn-outline-primary btn-sm ms-auto"><i class="ti ti-edit me-1"></i>Sửa lớp, thời hạn, giáo viên</a>@endif
    </div>
    @include('partials.classroom-period', ['classroom' => $classroom])

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Giáo viên</h3></div>
                <div class="list-group list-group-flush">
                    @forelse ($classroom->teachers as $t)
                        <div class="list-group-item d-flex flex-wrap gap-2 align-items-center">
                            <span class="fw-semibold">{{ $t->name }}</span>@if ($t->id === $classroom->teacher_id)<span class="badge bg-blue-lt">chính</span>@endif
                            @foreach (\App\Models\Classroom::PERMS as $k => $label)@if ($t->pivot->{$k})<span class="badge bg-green-lt">{{ $label }}</span>@endif @endforeach
                        </div>
                    @empty
                        <div class="list-group-item text-muted">Chưa gán giáo viên.</div>
                    @endforelse
                </div>
            </div>

            @include('custom.class.card', ['classroom' => $classroom, 'prefix' => 'admin.classrooms', 'canManage' => $canEdit])

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Học viên ({{ $classroom->students->count() }})</h3></div>
                <div class="table-responsive">
                    <table class="table table-sm table-vcenter card-table">
                        <thead><tr><th>Họ tên</th><th>Email</th><th>Điện thoại</th><th>Vào lớp</th><th class="w-1"></th></tr></thead>
                        <tbody>
                            @forelse ($classroom->students as $st)
                                <tr>
                                    <td>{{ $st->full_name ?: '—' }}@if ($st->must_change_password)<span class="badge bg-yellow-lt ms-1" title="Chưa đổi mật khẩu lần đầu">chưa đăng nhập</span>@endif</td>
                                    <td class="small">{{ $st->email }}</td>
                                    <td class="small">{{ $st->phone }}</td>
                                    <td class="small text-muted">{{ $st->pivot->created_at?->format('d/m/Y') }}</td>
                                    <td>
                                        @if ($canEdit)
                                            <div class="d-flex gap-1">
                                                <form method="POST" action="{{ route('admin.classrooms.students.reset', [$classroom, $st]) }}" onsubmit="return confirm('Cấp mật khẩu mới cho {{ $st->full_name }}?')">@csrf<button class="btn btn-sm btn-ghost-secondary" title="Cấp lại mật khẩu"><i class="ti ti-key"></i></button></form>
                                                <form method="POST" action="{{ route('admin.classrooms.students.remove', [$classroom, $st]) }}" onsubmit="return confirm('Xoá {{ $st->full_name }} khỏi lớp?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger" title="Xoá khỏi lớp"><i class="ti ti-x"></i></button></form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">Chưa có học viên.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Nhật ký tài khoản của lớp</h3></div>
                <div class="table-responsive">
                    <table class="table table-sm card-table">
                        <thead><tr><th>Lúc</th><th>Người làm</th><th>Việc</th><th>Học viên</th></tr></thead>
                        <tbody>
                            @forelse ($logs as $log)
                                <tr><td class="small text-muted">{{ $log->created_at?->format('H:i d/m/Y') }}</td><td class="small">{{ $log->actor_name }} <span class="text-muted">({{ ['user' => 'quản trị', 'teacher' => 'giáo viên', 'student' => 'học viên'][$log->actor_type] ?? $log->actor_type }})</span></td>
                                    <td class="small">{{ \App\Models\AccountLog::ACTIONS[$log->action] ?? $log->action }}</td><td class="small">{{ $log->student?->full_name ?? '—' }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="text-muted small">Chưa có thao tác.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            @if ($canEdit)
                @include('partials.lesson-schedule-card', ['classroom' => $classroom, 'action' => route('admin.classrooms.lesson-schedule', $classroom)])
                @include('partials.student-search-card', ['searchUrl' => route('admin.classrooms.students.search', $classroom), 'addUrl' => route('admin.classrooms.students.add-existing', $classroom)])
                @include('partials.student-import-card', ['action' => route('admin.classrooms.import', $classroom), 'template' => route('admin.students.import-template')])
            @endif
            @if ($classroom->allow_join)
                <div class="card mt-3"><div class="card-body">
                    <div class="small text-muted mb-1">Link mời (đang bật)</div>
                    <input class="form-control form-control-sm font-monospace" value="{{ $classroom->inviteUrl() }}" readonly onclick="this.select()">
                </div></div>
            @endif
            @if (auth()->user()->hasPermission('learning', 'delete'))
                <form method="POST" action="{{ route('admin.classrooms.destroy', $classroom) }}" class="mt-3 text-end" onsubmit="return confirm('Xoá lớp này? Các bài giao của lớp sẽ bị xoá.')">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">Xoá lớp</button>
                </form>
            @endif
        </div>
    </div>
@endsection
