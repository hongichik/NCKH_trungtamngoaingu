@extends('layouts.admin')

@section('title', $student->full_name ?: 'Học viên #'.$student->id)

@section('content')
    @php $canEdit = auth()->user()->hasPermission('learning', 'edit'); @endphp
    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('admin.student-accounts.index') }}"><i class="ti ti-arrow-left me-1"></i>Tài khoản học viên</a>
        @if (! $student->password)<span class="badge bg-secondary-lt">Hồ sơ thí sinh — chưa có mật khẩu đăng nhập</span>@endif
        @if ($student->must_change_password)<span class="badge bg-yellow-lt">Chưa đổi mật khẩu lần đầu</span>@endif
        @if ($student->login_locked)<span class="badge bg-red-lt">Đang bị khoá đăng nhập</span>@endif
        <span class="small text-muted ms-auto">Đăng nhập gần nhất: {{ $student->last_login_at?->format('H:i d/m/Y') ?? 'chưa' }}</span>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('admin.student-accounts.update', $student) }}" class="card mb-3">@csrf @method('PUT')
                <fieldset @disabled(! $canEdit)>
                    <div class="card-header"><h3 class="card-title">Thông tin</h3></div>
                    <div class="card-body row g-2">
                        <div class="col-md-6"><label class="form-label">Họ tên</label><input name="name" class="form-control" value="{{ old('name', $student->full_name) }}" required></div>
                        <div class="col-md-6"><label class="form-label">Email (tên đăng nhập)</label><input type="email" name="email" class="form-control" value="{{ old('email', $student->email) }}"></div>
                        <div class="col-md-6"><label class="form-label">Số điện thoại</label><input name="phone" class="form-control" value="{{ old('phone', $student->phone) }}"></div>
                        <div class="col-md-6"><label class="form-label">Ngày sinh</label><input type="date" name="dob" class="form-control" value="{{ old('dob', $student->dob?->format('Y-m-d')) }}"></div>
                    </div>
                    <div class="card-footer"><button class="btn btn-primary">Lưu</button></div>
                </fieldset>
            </form>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Lớp học ({{ $student->classrooms->count() }})</h3></div>
                <div class="list-group list-group-flush">
                    @forelse ($student->classrooms as $c)
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <a href="{{ route('admin.classrooms.show', $c) }}" class="flex-fill">{{ $c->name }}</a>
                            <span class="small text-muted">{{ $c->course?->title }}</span>
                            <span class="badge bg-secondary-lt">{{ \App\Models\Classroom::STATUSES[$c->status()] }}</span>
                            @if ($canEdit)
                                <form method="POST" action="{{ route('admin.student-accounts.classes.remove', [$student, $c]) }}" onsubmit="return confirm('Xoá khỏi lớp {{ $c->name }}?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger"><i class="ti ti-x"></i></button></form>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-muted">Chưa vào lớp nào.</div>
                    @endforelse
                </div>
                @if ($canEdit && $classrooms->isNotEmpty())
                    <form method="POST" action="{{ route('admin.student-accounts.classes.add', $student) }}" class="card-footer d-flex gap-2">@csrf
                        <select name="classroom_id" class="form-select" required><option value="">Chọn lớp…</option>@foreach ($classrooms as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                        <button class="btn btn-outline-primary text-nowrap" @disabled(blank($student->email))>Thêm vào lớp</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="col-lg-5">
            @if ($canEdit)
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title">Đăng nhập</h3></div>
                    <div class="card-body d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.student-accounts.reset', $student) }}" onsubmit="return confirm('{{ $student->password ? 'Cấp mật khẩu mới? Mật khẩu cũ sẽ không dùng được.' : 'Cấp mật khẩu để hồ sơ này đăng nhập được?' }}')">@csrf
                            <button class="btn btn-outline-primary" @disabled(blank($student->email))><i class="ti ti-key me-1"></i>{{ $student->password ? 'Cấp lại mật khẩu' : 'Cấp tài khoản' }}</button>
                        </form>
                        @if ($student->password)
                            <form method="POST" action="{{ route('admin.student-accounts.lock', $student) }}">@csrf
                                <button class="btn {{ $student->login_locked ? 'btn-success' : 'btn-outline-danger' }}"><i class="ti {{ $student->login_locked ? 'ti-lock-open' : 'ti-lock' }} me-1"></i>{{ $student->login_locked ? 'Mở khoá đăng nhập' : 'Khoá đăng nhập' }}</button>
                            </form>
                        @endif
                    </div>
                    @if (blank($student->email))<div class="card-footer small text-warning">Nhập email ở phần Thông tin trước khi cấp tài khoản / thêm vào lớp.</div>@endif
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h3 class="card-title">Nhật ký tài khoản</h3></div>
                <div class="list-group list-group-flush" style="max-height: 420px; overflow: auto">
                    @forelse ($logs as $log)
                        <div class="list-group-item small">
                            <div><b>{{ \App\Models\AccountLog::ACTIONS[$log->action] ?? $log->action }}</b>@if ($log->classroom) · {{ $log->classroom->name }}@endif</div>
                            <div class="text-muted">{{ $log->created_at?->format('H:i d/m/Y') }} · {{ $log->actor_name }} ({{ ['user' => 'quản trị', 'teacher' => 'giáo viên', 'student' => 'học viên', 'system' => 'hệ thống'][$log->actor_type] ?? $log->actor_type }})</div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted small">Chưa có thao tác.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
