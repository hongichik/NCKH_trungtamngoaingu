@extends('layouts.teacher')

@section('title', $classroom->name)
@section('pretitle', 'Lớp học · '.($classroom->course?->title ?? ''))



@section('content')
    @include('partials.classroom-period', ['classroom' => $classroom])
    <div class="row row-cards mb-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Quyền của bạn ở lớp này</div>
                    @foreach (\App\Models\Classroom::PERMS as $k => $label)
                        <div><i class="ti {{ $perms->{$k} ? 'ti-check text-success' : 'ti-x text-muted' }} me-1"></i>{{ $label }}</div>
                    @endforeach
                    <div class="small text-muted mt-1">Giao bài, chấm bài, xem bảng điểm: luôn được. Quyền khác do quản trị viên cấp.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-body">
                    @if ($classroom->allow_join)
                        <div class="text-muted small mb-1">Link mời vào lớp (quản trị viên đã bật)</div>
                        <div class="input-group">
                            <input class="form-control form-control-sm font-monospace" id="invite-link" value="{{ $classroom->inviteUrl() }}" readonly>
                            <button type="button" class="btn btn-sm btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('invite-link').value); this.textContent='Đã chép'"><i class="ti ti-copy"></i> Chép</button>
                        </div>
                    @else
                        <div class="text-muted small">Học viên vào lớp khi được thêm tài khoản. Link mời đang tắt (chỉ quản trị viên bật được).</div>
                    @endif
                    <form method="POST" action="{{ route('teacher.classrooms.update', $classroom) }}" class="mt-2">@csrf @method('PUT')
                        <label class="form-check form-switch mb-0 d-inline-block" title="Học viên thấy bảng xếp hạng XP 7 ngày của lớp"><input class="form-check-input" type="checkbox" name="show_leaderboard" value="1" @checked($classroom->show_leaderboard) onchange="this.form.submit()"><span class="form-check-label">Bảng xếp hạng lớp</span></label>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        @include('custom.class.card', ['classroom' => $classroom, 'prefix' => 'teacher.classrooms', 'canManage' => (bool) $perms->can_customize])

    <div class="row row-cards">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Học viên ({{ $classroom->students->count() }})</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-sm">
                        <thead><tr><th>Họ tên</th><th>Email</th><th>Vào lớp</th><th class="w-1"></th></tr></thead>
                        <tbody>
                            @forelse ($classroom->students as $st)
                                <tr>
                                    <td>{{ $st->full_name ?: '—' }}</td>
                                    <td class="text-muted small">{{ $st->email }}</td>
                                    <td class="text-muted small">{{ $st->pivot->created_at?->format('d/m/Y') }}</td>
                                    <td>
                                        <div class="d-flex gap-1 justify-content-end">
                                            @if ($perms->can_reset_password)
                                                <form method="POST" action="{{ route('teacher.classrooms.students.reset', [$classroom, $st]) }}" onsubmit="return confirm('Cấp mật khẩu mới cho {{ $st->full_name }}? Mật khẩu cũ sẽ không dùng được nữa.')">@csrf
                                                    <button class="btn btn-sm btn-ghost-secondary" title="Cấp lại mật khẩu"><i class="ti ti-key"></i></button>
                                                </form>
                                            @endif
                                            @if ($perms->can_add_students)
                                                <form method="POST" action="{{ route('teacher.classrooms.students.remove', [$classroom, $st]) }}" onsubmit="return confirm('Xoá {{ $st->full_name }} khỏi lớp?')">@csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-ghost-danger" title="Xoá khỏi lớp"><i class="ti ti-x"></i></button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Chưa có học viên. Gửi link mời hoặc thêm bằng email.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            @if ($perms->can_customize)
                @include('partials.lesson-schedule-card', ['classroom' => $classroom, 'action' => route('teacher.classrooms.lesson-schedule', $classroom)])
            @endif
            @if ($perms->can_add_students)
                @include('partials.student-search-card', ['searchUrl' => route('teacher.classrooms.students.search', $classroom), 'addUrl' => route('teacher.classrooms.students.add-existing', $classroom)])
                @include('partials.student-import-card', ['action' => route('teacher.classrooms.import', $classroom), 'template' => route('teacher.students.import-template')])
            @endif
        </div>
    </div>
@endsection
