@extends('layouts.portal')

@section('title', 'Kết quả: '.$set->title)

@php
    $pct = $set->gradedByAi() ? $attempt->score / 10 : ($attempt->total ? $attempt->correct / $attempt->total : 0);
    $cls = $pct >= 0.8 ? 'success' : ($pct >= 0.5 ? 'warning' : 'danger');
@endphp

@section('content')
    @php $cls = $attempt->unitTest?->assignment?->classroom; @endphp
    <nav class="small mb-2"><a href="{{ route('portal.classes.index') }}">Lớp học của tôi</a>@if ($cls) / <a href="{{ route('portal.classes.show', $cls) }}">{{ $cls->name }}</a>@endif / Chi tiết</nav>
    <h1 class="h4 mb-3">{{ $set->title }}</h1>

    @if ($attempt->status === 'grading')
        <div class="card border-0 shadow-sm mb-4"><div class="card-body text-center py-5">
            <div class="spinner-border text-primary mb-2"></div>
            <div class="fw-semibold">AI đang chấm bài {{ $set->skill === 'speaking' ? 'Nói' : 'Viết' }} của bạn…</div>
            <div class="small text-muted">Thường mất 10–60 giây. Trang sẽ tự cập nhật.</div>
        </div></div>
        @push('scripts')
        <script>
            (function poll() {
                fetch(@json(route('portal.learning.result-status', [$course, $attempt])), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then((r) => r.json()).then((j) => { j.status === 'grading' ? setTimeout(poll, 3000) : location.reload(); })
                    .catch(() => setTimeout(poll, 5000));
            })();
        </script>
        @endpush
    @elseif ($attempt->status === 'failed')
        <div class="alert alert-danger">Chưa chấm được bài ({{ $attempt->error }}). Hệ thống sẽ thử lại; bạn cũng có thể làm lại bài.</div>
    @else
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="display-6 fw-bold text-{{ $cls }}">{{ rtrim(rtrim(number_format($attempt->score, 1), '0'), '.') }}<span class="fs-5 text-muted">/10</span></div>
            <div>
                @if ($set->gradedByAi())
                    Điểm trung bình các phần
                @else
                    Đúng <strong>{{ $attempt->correct }}/{{ $attempt->total }}</strong> câu
                @endif
                <br><span class="small text-muted">{{ $pct >= 0.8 ? 'Rất tốt! 🎉' : ($pct >= 0.5 ? 'Khá tốt, xem lại các câu sai nhé.' : 'Cố lên! Đọc kỹ phần giải thích rồi làm lại.') }}</span></div>
            <div class="ms-auto d-flex gap-2">
                @if ($attempt->unit_test_id)
                    <a href="{{ route('portal.learning.test-result', [$course, $attempt->unit_test_id]) }}" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Kết quả cả bài</a>
                @endif
                <a href="{{ $cls ? route('portal.classes.show', $cls) : route('portal.classes.index') }}" class="btn btn-outline-secondary">Về lớp học</a>
            </div>
        </div>
    </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            @foreach ($attempt->result as $r)
                <div class="card border-0 shadow-sm mb-3 border-start border-4 border-{{ $r['correct'] ? 'success' : 'danger' }}">
                    <div class="card-body">
                        <div class="fw-semibold mb-1">
                            <i class="bi {{ $r['correct'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }}"></i>
                            @if (in_array($r['type'], ['speaking', 'read_aloud', 'paragraph'], true))<span class="badge text-bg-light">{{ rtrim(rtrim(number_format($r['score'] ?? 0, 1), '0'), '.') }}/10</span>@endif
                            <span class="badge text-bg-secondary">{{ $r['code'] }}</span> {{ $r['prompt'] }}
                        </div>
                        @if (in_array($r['type'], ['speaking', 'read_aloud', 'paragraph', 'transformation'], true))
                            @include('portal.learning.partials.open-result', ['r' => $r, 'lang' => $course->language])
                        @else
                        @if (! empty($r['options']))<div class="small text-muted mb-1">{{ implode(' · ', $r['options']) }}</div>@endif
                        <div class="small">Bạn chọn: <strong class="text-{{ $r['correct'] ? 'success' : 'danger' }}">{{ $r['given'] !== '' ? $r['given'] : '(bỏ trống)' }}</strong>
                            @unless ($r['correct']) · Đáp án đúng: <strong class="text-success">{{ $r['answer'] }}</strong>@endunless</div>
                        @if (! empty($r['explanation']))<div class="small mt-1"><i class="bi bi-lightbulb text-warning"></i> {{ $r['explanation'] }}</div>@endif
                        @if (! empty($r['evidence']))<div class="small mt-1 fst-italic text-muted">Căn cứ trong bài: “{{ $r['evidence'] }}”</div>@endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <div class="col-lg-5">
            @if ($set->skill === 'reading')
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold">Bài đọc</div>
                    <div class="card-body small" style="white-space: pre-wrap; max-height: 60vh; overflow: auto">{{ $passage }}</div>
                </div>
            @elseif ($set->skill === 'listening')
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold">Lời thoại bài nghe</div>
                    <div class="card-body small">
                        @foreach ($audioSrcs as $src)<audio controls preload="none" src="{{ $src }}" class="w-100 mb-2"></audio>@endforeach
                        @foreach ($script as $i => $l)
                            <div class="mb-1 d-flex gap-1">
                                @if (! empty($tts[$i]))<button type="button" class="btn btn-sm btn-link p-0 replay" data-src="{{ $tts[$i] }}" title="Nghe lại câu này"><i class="bi bi-play-circle"></i></button>
                                @else<button type="button" class="btn btn-sm btn-link p-0 replay-tts" data-text="{{ $l['text'] }}" title="Nghe lại câu này"><i class="bi bi-play-circle"></i></button>@endif
                                <span><strong>{{ $l['speaker'] }}:</strong> {{ $l['text'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Các lần làm</div>
                <ul class="list-group list-group-flush small">
                    @foreach ($history as $h)
                        <li class="list-group-item d-flex {{ $h->id === $attempt->id ? 'fw-semibold' : '' }}"><span>{{ $h->created_at->format('d/m H:i') }}</span><span class="ms-auto">{{ rtrim(rtrim(number_format($h->score, 1), '0'), '.') }}/10</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/speech.js') }}?v=2"></script>
<script>
document.querySelectorAll('.replay').forEach((b) => b.addEventListener('click', () => new Audio(b.dataset.src).play()));
document.querySelectorAll('.replay-tts').forEach((b) => b.addEventListener('click', () => AppSpeech.speakNow(b.dataset.text, @json($course->language === 'zh' ? 'zh-CN' : 'en-US'), 0.9)));
</script>
@endpush
