@extends('layouts.portal')

@section('title', 'Kết quả: '.$exercise->title)

@section('content')
    <nav class="small mb-2"><a href="{{ route('portal.classes.show', $cce->classroom) }}">{{ $cce->classroom->name }}</a> / Kết quả</nav>
    <h1 class="h4 mb-3">{{ $exercise->title }}</h1>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            @if ($attempt->status === 'grading')
                <div class="fs-5"><span class="spinner-border spinner-border-sm"></span> Đang chấm phần Viết / Nói…</div>
                <div class="small text-muted">Điểm phần máy chấm: {{ rtrim(rtrim(number_format((float) $attempt->earned, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format((float) $attempt->max, 2), '0'), '.') }}. Trang tự cập nhật khi chấm xong (AI hoặc giáo viên).</div>
            @else
                <div class="display-6 fw-bold">{{ number_format($attempt->finalScore() ?? 0, 1) }}<span class="fs-5 text-muted"> / 10</span></div>
                <div class="small text-muted">{{ rtrim(rtrim(number_format((float) $attempt->earned, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format((float) $attempt->max, 2), '0'), '.') }} điểm
                    @if ($attempt->teacher_score !== null) · Giáo viên đã sửa điểm @endif</div>
            @endif
            @if ($attempt->teacher_comment)<div class="w-100 alert alert-info mb-0"><b>Nhận xét của giáo viên:</b> {{ $attempt->teacher_comment }}</div>@endif
        </div>
    </div>

    @foreach ($exercise->items as $item)
        @php $r = ($attempt->result ?? [])[$item->id] ?? null; $t = $item->type(); @endphp
        @if ($r && $t)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex">
                    <span class="fw-semibold flex-fill">Phần {{ $loop->iteration }}{{ $item->title ? ': '.$item->title : '' }}</span>
                    <span>{{ $r['pending'] ? 'Chờ chấm' : ($r['score']['scaled'] ?? '') }} · {{ rtrim(rtrim(number_format($r['score']['earned'], 2), '0'), '.') }}/{{ rtrim(rtrim(number_format($r['score']['max'], 2), '0'), '.') }}</span>
                </div>
                @if (! empty($r['feedback']))
                    <div class="card-body border-bottom">
                        @foreach ($r['feedback']['criteria'] ?? [] as $c)<div class="small"><b>{{ $c['name'] }}: {{ $c['score'] }}</b> — {{ $c['comment'] }}</div>@endforeach
                        @if (! empty($r['feedback']['comment']))<div class="mt-1">{{ $r['feedback']['comment'] }}</div>@endif
                        @if (! empty($r['feedback']['transcript']))<div class="small text-muted mt-1">Bản chép lời: “{{ $r['feedback']['transcript'] }}”</div>@endif
                    </div>
                @endif
                @if ($showAnswers && ! in_array($t->engine, ['writing', 'speaking'], true))
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Câu</th><th>Bạn trả lời</th><th>Đáp án</th></tr></thead>
                            <tbody>
                                @foreach ($r['parts'] as $p)
                                    <tr class="{{ $p['correct'] ? 'table-success' : 'table-danger' }}"><td>{{ $p['label'] }}</td><td>{{ $p['given'] ?: '—' }}{{ $p['reason'] ? ' ('.$p['reason'].')' : '' }}</td><td>{{ $p['expected'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif (! $showAnswers)
                    <div class="card-body small text-muted">Đáp án sẽ hiện {{ $cce->show_answers === 'after_due' ? 'sau hạn nộp' : 'khi giáo viên cho phép' }}. Đúng {{ collect($r['parts'])->where('correct', true)->count() }}/{{ count($r['parts']) }} ý.</div>
                @endif
            </div>
        @endif
    @endforeach
    {{-- Phần đã bị giáo viên bỏ khỏi bài sau khi bạn nộp: vẫn hiện để tổng điểm khớp --}}
    @foreach (collect($attempt->result ?? [])->except($exercise->items->pluck('id')->all()) as $r)
        <div class="card border-0 shadow-sm mb-3 opacity-75">
            <div class="card-header bg-white d-flex">
                <span class="fw-semibold flex-fill">{{ $r['title'] ?? 'Phần đã bỏ khỏi bài' }} <span class="badge text-bg-light border">đã bỏ khỏi bài</span></span>
                <span>{{ rtrim(rtrim(number_format($r['score']['earned'] ?? 0, 2), '0'), '.') }}/{{ rtrim(rtrim(number_format($r['score']['max'] ?? 0, 2), '0'), '.') }}</span>
            </div>
        </div>
    @endforeach
    <a href="{{ route('portal.classes.show', $cce->classroom) }}" class="btn btn-outline-primary">Về lớp</a>
@endsection

@if ($attempt->status === 'grading')
    @push('scripts')
    <script>
    setInterval(async () => { const r = await fetch(@json(route('portal.custom.status', $attempt)), { headers: { Accept: 'application/json' } }); const j = await r.json(); if (j.status === 'graded') location.reload(); }, 8000);
    </script>
    @endpush
@endif
