@extends('layouts.portal')

@section('title', $classroom->name)

@php
    $st = ['todo' => ['Chưa làm', 'text-bg-warning'], 'in_progress' => ['Đang làm', 'text-bg-primary'], 'done' => ['Đã nộp', 'text-bg-success'], 'missed' => ['Quá hạn', 'text-bg-danger'], 'upcoming' => ['Chưa mở', 'text-bg-secondary']];
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $levelCls = ['Xuất sắc' => 'text-bg-success', 'Giỏi' => 'text-bg-success', 'Khá' => 'text-bg-primary', 'Trung bình' => 'text-bg-warning', 'Chưa đạt' => 'text-bg-danger'];
    $skillLabels = \App\Models\ExerciseSet::SKILLS;
@endphp

@section('content')
    <nav class="small mb-2"><a href="{{ route('portal.classes.index') }}">Lớp học của tôi</a> / {{ $classroom->name }}</nav>
    <h1 class="h4 mb-1">{{ $classroom->name }}</h1>
    <div class="small text-muted mb-3">{{ $classroom->course?->title }} · Giáo viên: {{ $classroom->teacher?->name }}</div>
    @include('partials.classroom-period', ['classroom' => $classroom, 'student' => true])
    @php $classOpen = $classroom->accessOpen(); @endphp
    @if ($lessons->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-collection-play"></i> Bài học ({{ $lessons->count() }}) <span class="small text-muted fw-normal">— mở bài học để làm bài tập</span></div>
            <div class="list-group list-group-flush">
                @foreach ($lessons as $l)
                    @if ($l->open_now && $classOpen)
                        <a href="{{ route('portal.lessons.show', [$classroom, $l]) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <span class="text-muted">{{ $loop->iteration }}.</span><span class="flex-fill">{{ $l->title }}</span>
                            <span class="small text-muted"><i class="bi bi-ui-checks"></i> {{ $l->exercise_count }} bài tập</span>
                            @if ($l->videos_count)<span class="small {{ $l->videos_done === $l->videos_count ? 'text-success' : 'text-muted' }}"><i class="bi bi-play-btn"></i> {{ $l->videos_done }}/{{ $l->videos_count }}</span>@endif
                        </a>
                    @else
                        <div class="list-group-item d-flex align-items-center gap-2 text-muted">
                            <span>{{ $loop->iteration }}.</span><span class="flex-fill">{{ $l->title }}</span>
                            <span class="small"><i class="bi bi-lock"></i> {{ $l->opens_at_for_class && $l->opens_at_for_class->isFuture() ? 'Mở '.$l->opens_at_for_class->format('H:i d/m') : 'Chưa mở' }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
    @stack('class-top')
    @if ($lessons->isEmpty() && $assignments->isEmpty())<div class="alert alert-info">Lớp chưa có bài học nào.</div>@endif

    @foreach ($assignments as $a)
        @php
            $status = $a->statusFor($studentId);
            $history = $a->tests->whereIn('status', ['completed', 'grading']);
            $inProgress = $a->tests->firstWhere('status', 'in_progress');
            $canStart = $classOpen && (in_array($status, ['todo', 'in_progress'], true) || ($status === 'done' && $a->isOpen() && (! $a->max_attempts || $a->attemptsUsed($studentId) < $a->max_attempts)));
            $p = $a->kind === 'mock' ? [] : ($a->unit->profile?->data ?? []); // đề thi thử: không hiện tài liệu Unit
            $course = $classroom->course;
        @endphp
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div class="flex-fill">
                        <div class="d-flex flex-wrap gap-1 mb-1">
                            <span class="badge {{ $a->mode === 'test' ? 'text-bg-danger' : 'text-bg-info' }}">{{ \App\Models\Assignment::MODES[$a->mode] }}</span>
                            @if ($a->kind === 'mock')<span class="badge text-bg-dark">Đề thi thử</span>@endif
                            <span class="badge {{ $st[$status][1] }}">{{ $st[$status][0] }}</span>
                        </div>
                        <div class="fw-semibold fs-5">{{ $a->title }}</div>
                        <div class="small text-muted">
                            {{ collect($a->sections)->map(fn ($s) => $skillLabels[$s['skill']])->implode(' · ') }}
                            @if ($a->opens_at) · Mở {{ $a->opens_at->format('H:i d/m') }}@endif
                            @if ($a->due_at) · <span class="{{ $status === 'todo' && $a->due_at->diffInHours(now()) < 24 ? 'text-danger fw-semibold' : '' }}">Hạn {{ $a->due_at->format('H:i d/m/Y') }}</span>@endif
                            @if ($a->time_limit_minutes) · {{ $a->time_limit_minutes }} phút @endif
                            @if ($a->max_attempts) · Lượt {{ $a->attemptsUsed($studentId) }}/{{ $a->max_attempts }} @endif
                        </div>
                    </div>
                    @if ($canStart)
                        <form method="POST" action="{{ route('portal.assignments.start', $a) }}">@csrf
                            <button class="btn btn-primary" @if ($a->time_limit_minutes && ! $inProgress) onclick="return confirm('Bài có giới hạn {{ $a->time_limit_minutes }} phút, đồng hồ bắt đầu chạy khi vào bài. Bắt đầu?')" @endif>
                                <i class="bi bi-pencil-square"></i> {{ $inProgress ? 'Làm tiếp' : ($status === 'done' ? 'Làm lại' : 'Làm bài') }}
                            </button>
                        </form>
                    @endif
                </div>

                <div class="d-flex flex-wrap gap-2 mt-2">
                    @if (! empty($p['vocabulary']))<a href="{{ route('portal.learning.material', [$course, $a->unit, 'tu-vung', 'lop' => $classroom->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-card-text"></i> Xem từ vựng</a>@endif
                    @if (! empty($p['grammar_points']))<a href="{{ route('portal.learning.material', [$course, $a->unit, 'ngu-phap', 'lop' => $classroom->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-diagram-3"></i> Xem ngữ pháp</a>@endif
                    @if ($course?->show_reading_preview && (! empty($p['passages']) || ! empty($p['dialogues'])))<a href="{{ route('portal.learning.material', [$course, $a->unit, 'bai-doc', 'lop' => $classroom->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-book"></i> Xem trước bài đọc</a>@endif
                    @if (! empty($p['vocabulary']) && (! $a->opens_at || $a->opens_at->isPast()))<a href="{{ route('portal.vocab.study', ['unit' => $a->unit_id]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-card-heading"></i> Học thẻ từ</a>@endif
                    @if (! empty($p['vocabulary']) && $course?->language === 'zh' && (! $a->opens_at || $a->opens_at->isPast()))<a href="{{ route('portal.vocab.writing', $a->unit_id) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-brush"></i> Tập viết chữ Hán</a>@endif
                </div>

                @if ($history->isNotEmpty())
                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Lần</th><th>Ngày làm</th><th class="text-center">Điểm tổng</th><th>Trình độ</th><th class="d-none d-md-table-cell">Từng kỹ năng</th><th></th></tr></thead>
                            @foreach ($history as $t)
                                <tr>
                                    <td class="text-muted">{{ $history->count() - $loop->index }}</td>
                                    <td class="small">{{ ($t->finished_at ?? $t->updated_at)->format('H:i d/m/Y') }}</td>
                                    <td class="text-center fw-bold">{{ $t->status === 'completed' ? $fmt($t->total_score) : '…' }}</td>
                                    <td>@if ($t->status === 'completed')<span class="badge {{ $levelCls[$t->level] ?? 'text-bg-secondary' }}">{{ $t->level }}</span>@else<span class="badge text-bg-light">Đang chấm</span>@endif
                                        @if ($t->teacher_reviewed)<span class="badge text-bg-info ms-1" title="Giáo viên đã sửa điểm / nhận xét"><i class="bi bi-person-check"></i> GV đã chấm lại</span>@endif</td>
                                    <td class="small text-muted d-none d-md-table-cell">@foreach ($t->skill_scores ?? [] as $sk => $sc){{ $skillLabels[$sk] }} {{ $fmt($sc) }}@if (! $loop->last) · @endif @endforeach</td>
                                    <td><a href="{{ route('portal.learning.test-result', [$t->course_id, $t]) }}" class="btn btn-sm btn-outline-secondary">Xem lại</a></td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @if ($board && $board->isNotEmpty())
        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-bar-chart-steps"></i> Bảng xếp hạng XP 7 ngày</div>
            <ol class="list-group list-group-flush list-group-numbered">
                @foreach ($board as $r)
                    <li class="list-group-item d-flex {{ $r['student']->id === $studentId ? 'fw-semibold bg-light' : '' }}"><span class="ms-2">{{ $r['student']->full_name ?: 'Học viên' }}</span><span class="ms-auto">{{ $r['xp'] }} XP</span></li>
                @endforeach
            </ol>
        </div>
    @endif
@endsection
