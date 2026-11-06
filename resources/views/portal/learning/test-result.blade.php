@extends('layouts.portal')

@section('title', 'Kết quả: '.$test->unit->title)

@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $skillLabels = \App\Models\ExerciseSet::SKILLS;
    $icons = ['reading' => 'book', 'listening' => 'headphones', 'speaking' => 'mic', 'writing' => 'pencil'];
    $cls = $test->total_score >= 8 ? 'success' : ($test->total_score >= 5 ? 'warning' : 'danger');
@endphp

@section('content')
    @php $cls = $test->assignment?->classroom; @endphp
    <nav class="small mb-2"><a href="{{ route('portal.classes.index') }}">Lớp học của tôi</a>@if ($cls) / <a href="{{ route('portal.classes.show', $cls) }}">{{ $cls->name }}</a>@endif / Kết quả</nav>
    <h1 class="h4 mb-3">Kết quả bài làm — {{ $test->assignment?->kind === 'mock' ? $test->assignment->title : $test->unit->title }}</h1>

    @if ($test->status === 'in_progress')
        <div class="alert alert-info">Bài làm chưa nộp. <a href="{{ route('portal.learning.test', [$course, $test]) }}">Tiếp tục làm bài</a></div>
    @elseif ($test->status === 'grading')
        <div class="card border-0 shadow-sm mb-4"><div class="card-body text-center py-5">
            <div class="spinner-border text-primary mb-2"></div>
            <div class="fw-semibold">Đã nộp đủ các phần. AI đang chấm phần Nói / Viết…</div>
            <div class="small text-muted">Thường mất dưới 1 phút. Trang sẽ tự cập nhật.</div>
        </div></div>
        @push('scripts')
        <script>
            (function poll() {
                fetch(@json(route('portal.learning.test-status', [$course, $test])), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then((r) => r.json()).then((j) => { j.status === 'grading' ? setTimeout(poll, 3000) : location.reload(); })
                    .catch(() => setTimeout(poll, 5000));
            })();
        </script>
        @endpush
    @else
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body d-flex flex-wrap align-items-center gap-4">
                <div class="text-center">
                    <div class="display-5 fw-bold text-{{ $cls }}">{{ $fmt($test->total_score) }}<span class="fs-5 text-muted">/10</span></div>
                    <div class="small text-muted">Điểm tổng</div>
                </div>
                <div>
                    <div class="small text-muted">Trình độ</div>
                    <div class="fs-4 fw-semibold">{{ $test->level }}</div>
                    <div class="small text-muted">{{ $test->finished_at?->format('d/m/Y H:i') }}</div>
                </div>
                <div class="ms-auto d-flex gap-2">
                    <a href="{{ $cls ? route('portal.classes.show', $cls) : route('portal.classes.index') }}" class="btn btn-outline-secondary">Về lớp học</a>
                </div>
            </div>
        </div>
    @endif

    @if (! $answersVisible)
        <div class="alert alert-light border small">Đáp án và giải thích chi tiết sẽ hiện sau hạn nộp ({{ $test->assignment->due_at?->format('H:i d/m/Y') }}).</div>
    @endif
    <div class="row g-3">
        @foreach ($test->sections as $i => $s)
            @php $a = $attempts[$s['attempt_id']] ?? null; @endphp
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Phần {{ $i + 1 }}</div>
                        <div class="fw-semibold mb-2"><i class="bi bi-{{ $icons[$s['skill']] }}"></i> {{ $skillLabels[$s['skill']] }}</div>
                        @if (! $a)
                            <div class="text-muted small">Chưa làm</div>
                        @elseif ($a->status === 'grading')
                            <div class="small"><span class="spinner-border spinner-border-sm"></span> Đang chấm…</div>
                        @elseif ($a->status === 'failed')
                            <div class="small text-danger">Chưa chấm được</div>
                        @else
                            <div class="fs-3 fw-bold">{{ $fmt($a->finalScore()) }}<span class="fs-6 text-muted">/10</span></div>
                            @unless (in_array($s['skill'], ['speaking', 'writing'], true))<div class="small text-muted">Đúng {{ $a->correct }}/{{ $a->total }} câu</div>@endunless
                        @endif
                        @if ($a && $a->teacher_score !== null)<div class="small text-primary"><i class="bi bi-person-check"></i> Giáo viên đã chấm lại</div>@endif
                        @if ($a && $a->teacher_comment)<div class="small fst-italic mt-1">“{{ $a->teacher_comment }}”</div>@endif
                        @if ($a && $a->status === 'graded' && $answersVisible)
                            <a href="{{ route('portal.learning.result', [$course, $a]) }}" class="btn btn-sm btn-outline-primary mt-2">Xem chi tiết</a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
