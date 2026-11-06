@extends('layouts.portal')

@section('title', 'Trang chủ thí sinh')

@php
    $types = ['hsk' => ['HSK (tiếng Trung)', 'translate', 'primary'], 'cntt' => ['Tin học (CNTT)', 'laptop', 'success']];
    $statusBadge = ['paid' => ['Đã thanh toán', 'badge-status-paid'], 'cancelled' => ['Đã huỷ', 'badge-status-cancelled']];
@endphp

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1">Xin chào, {{ $student->full_name ?: $student->email }} 👋</h1>
            <div class="text-muted small">{{ $student->email }}</div>
        </div>
    </div>

    @if ($classes->isEmpty())
        @unless ($profileDone)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
            <i class="bi bi-exclamation-triangle fs-5"></i>
            <span>Bạn chưa hoàn thiện hồ sơ thí sinh — cần có hồ sơ đầy đủ trước khi đăng ký thi.</span>
            <a href="{{ route('portal.profile.edit') }}" class="btn btn-sm btn-warning ms-auto">Hoàn thiện hồ sơ</a>
        </div>
    @endunless

    @endif

    {{-- Người đang học: lớp học, bài cần làm lên đầu; đăng ký thi xuống dưới --}}
    @if ($classes->isNotEmpty())
    {{-- Lớp học (chỉ khi đã ở trong lớp) --}}
    @if ($stat)
        @php $pct = min(100, round($todayXp / max(1, $stat->daily_goal) * 100)); @endphp
        <a href="{{ route('portal.progress.index') }}" class="card shadow-sm border-0 mb-3 text-decoration-none text-body">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <span class="fs-5"><i class="bi bi-fire text-danger"></i> <strong>{{ $streak }}</strong> ngày liên tiếp</span>
                <span class="fs-5"><i class="bi bi-lightning-charge-fill text-warning"></i> <strong>{{ number_format($stat->xp) }}</strong> XP</span>
                <div class="flex-fill" style="min-width: 180px">
                    <div class="small text-muted">Mục tiêu hôm nay: {{ $todayXp }}/{{ $stat->daily_goal }} XP</div>
                    <div class="progress" style="height: 8px"><div class="progress-bar {{ $pct >= 100 ? 'bg-success' : '' }}" style="width: {{ $pct }}%"></div></div>
                </div>
            </div>
        </a>
    @endif
    @if ($classes->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header bg-white d-flex align-items-center">
                <strong><i class="bi bi-people"></i> Lớp học của tôi</strong>
                <a href="{{ route('portal.classes.index') }}" class="small ms-auto">Xem tất cả</a>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach ($classes as $c)
                        <a href="{{ route('portal.classes.show', $c) }}" class="btn btn-sm btn-light border">{{ $c->name }}@if ($c->teacher) <span class="text-muted">· {{ $c->teacher->name }}</span>@endif</a>
                    @endforeach
                </div>
                @if ($vocabDue)
                    <a href="{{ route('portal.vocab.study') }}" class="alert alert-warning py-2 small d-block text-decoration-none"><i class="bi bi-card-heading"></i> Có <strong>{{ $vocabDue }}</strong> thẻ từ vựng đến hạn ôn — bấm để ôn ngay.</a>
                @endif
                @if ($reviewed->isNotEmpty())
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-person-check"></i> <strong>Giáo viên vừa chấm lại:</strong>
                        @foreach ($reviewed as $t)<a href="{{ route('portal.learning.test-result', [$t->course_id, $t]) }}" class="ms-1">{{ $t->assignment?->title }} ({{ $t->total_score !== null ? rtrim(rtrim(number_format($t->total_score, 1), '0'), '.') : '…' }} điểm)</a>@if (! $loop->last),@endif @endforeach
                    </div>
                @endif
                @if ($todo->isNotEmpty() || $lessonTodo->isNotEmpty())
                    <div class="small fw-semibold mb-2">Bài cần làm <span class="text-muted fw-normal">({{ $todo->count() + $lessonTodo->count() }})</span></div>
                    <div class="list-group">
                        @foreach ($lessonTodo->take(6) as $t)
                            <a href="{{ route('portal.lessons.show', [$t['classroom'], $t['lesson']]) }}" class="list-group-item list-group-item-action d-flex flex-wrap gap-2 align-items-center">
                                <i class="bi {{ $t['in_progress'] ? 'bi-hourglass-split text-warning' : 'bi-pencil-square text-primary' }}"></i>
                                <span>{{ $t['exercise']->title }}</span>
                                <span class="small text-muted">{{ $t['classroom']->name }}</span>
                                <span class="small ms-auto {{ $t['in_progress'] ? 'text-warning fw-semibold' : 'text-primary' }}">{{ $t['in_progress'] ? 'Đang làm dở — làm tiếp' : 'Làm bài' }} <i class="bi bi-chevron-right"></i></span>
                            </a>
                        @endforeach
                        @foreach ($todo as $a)
                            <a href="{{ route('portal.classes.show', $a->classroom_id) }}" class="list-group-item list-group-item-action d-flex flex-wrap gap-2 align-items-center">
                                <i class="bi bi-journal-check text-primary"></i>
                                <span>{{ $a->title }}</span>
                                <span class="small text-muted">{{ $a->classroom->name }}</span>
                                @if ($a->due_at)<span class="small ms-auto {{ $a->due_at->diffInHours(now(), true) < 24 ? 'text-danger fw-semibold' : 'text-muted' }}">Hạn {{ $a->due_at->format('H:i d/m') }}</span>@endif
                            </a>
                        @endforeach
                    </div>
                    @if ($lessonTodo->count() > 6)<div class="small text-muted mt-1">… và {{ $lessonTodo->count() - 6 }} bài nữa trong các bài học.</div>@endif
                @else
                    <div class="small text-success"><i class="bi bi-check-circle"></i> Bạn đã làm hết bài tập của các bài học đang mở. Giỏi lắm!</div>
                @endif
            </div>
        </div>
    @endif

    @endif

    @if ($classes->isNotEmpty())
        <h2 class="h6 text-muted text-uppercase small mb-2">Thi chứng chỉ</h2>
        @unless ($profileDone)
            <div class="small text-muted mb-2"><i class="bi bi-info-circle"></i> Muốn đăng ký thi chứng chỉ, bạn cần <a href="{{ route('portal.profile.edit') }}">hoàn thiện hồ sơ thí sinh</a> trước.</div>
        @endunless
    @endif
    {{-- Đăng ký thi --}}
    <div class="row g-3 mb-4">
        @foreach ($types as $type => [$label, $icon, $color])
            @php $sessions = $openSessions[$type] ?? collect(); @endphp
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge text-bg-{{ $color }} fs-6"><i class="bi bi-{{ $icon }}"></i></span>
                            <h2 class="h6 mb-0">Thi chứng chỉ {{ $label }}</h2>
                        </div>
                        @if ($sessions->isNotEmpty())
                            <ul class="small text-muted ps-3 mb-3">
                                @foreach ($sessions->take(3) as $s)
                                    <li>{{ $s->name }}@if ($s->exam_date) — thi ngày {{ $s->exam_date->format('d/m/Y') }}@endif @if ($s->registration_end) <span class="text-nowrap">(hạn ĐK {{ $s->registration_end->format('d/m/Y') }})</span>@endif</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="small text-muted mb-3">Hiện chưa có đợt thi đang mở đăng ký.</p>
                        @endif
                        <a href="{{ route('portal.exam.show', ['type' => $type]) }}" class="btn btn-outline-{{ $color }} mt-auto align-self-start">
                            <i class="bi bi-pencil-square"></i> Đăng ký thi
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>


    @if ($classes->isEmpty() || $registrations->isNotEmpty())
    {{-- Đăng ký gần đây --}}
    <div class="card">
        <div class="card-header bg-white d-flex align-items-center">
            <strong><i class="bi bi-clipboard-data"></i> Đăng ký dự thi gần đây</strong>
            <a href="{{ route('portal.results.index') }}" class="small ms-auto">Kết quả & chứng chỉ ({{ $certificateCount }})</a>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter mb-0">
                <tbody>
                    @forelse ($registrations as $r)
                        <tr>
                            <td><code>{{ $r->registration_code }}</code></td>
                            <td>{{ $r->exam_type === 'hsk' ? 'HSK' : 'Tin học' }}</td>
                            <td>{{ $r->examSession?->name }}</td>
                            <td>{{ $r->examLevel?->name }}</td>
                            <td>@php [$t, $cls] = $statusBadge[$r->status] ?? ['Chờ thanh toán', 'badge-status-pending']; @endphp<span class="badge {{ $cls }}">{{ $t }}</span></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted py-4">Bạn chưa đăng ký dự thi nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endsection
