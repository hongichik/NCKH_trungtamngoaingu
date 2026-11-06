@php $labels = ['task_achievement' => 'Hoàn thành nhiệm vụ', 'pronunciation' => 'Phát âm', 'fluency' => 'Trôi chảy', 'vocabulary' => 'Từ vựng', 'grammar' => 'Ngữ pháp', 'coherence' => 'Mạch lạc']; @endphp

@if ($r['type'] === 'transformation')
    <div class="small">Bạn viết: <strong class="text-{{ $r['correct'] ? 'success' : 'danger' }}">{{ $r['given'] !== '' ? $r['given'] : '(bỏ trống)' }}</strong></div>
    @unless ($r['correct'])<div class="small">Đáp án: <strong class="text-success">{{ $r['answer'] }}</strong></div>@endunless
    @if (! empty($r['comment_vi']))<div class="small mt-1"><i class="bi bi-chat-left-text text-primary"></i> {{ $r['comment_vi'] }}</div>@endif
    @if (! empty($r['explanation']))<div class="small mt-1"><i class="bi bi-lightbulb text-warning"></i> {{ $r['explanation'] }}</div>@endif

@elseif ($r['type'] === 'read_aloud')
    <div class="fs-5 mb-1">
        @foreach ($r['words'] ?? [] as $w)<span class="{{ $w['ok'] ? 'text-success' : 'text-danger text-decoration-underline' }}">{{ $w['t'] }}</span>{{ $lang === 'zh' ? '' : ' ' }}@endforeach
    </div>
    @if (! empty($r['transcript']))<div class="small text-muted">Máy nghe được: “{{ $r['transcript'] }}”</div>@endif
    <div class="small mt-1">{{ $r['feedback_vi'] ?? '' }} <span class="text-muted">(điểm phát âm chỉ để tham khảo)</span></div>

@else {{-- speaking / paragraph --}}
    @if (($r['status'] ?? 'ok') !== 'ok')
        <div class="alert alert-warning py-1 px-2 small mb-2">{{ ['too_short' => 'Quá ngắn / chưa làm', 'not_target_language' => 'Chưa dùng đúng ngôn ngữ', 'off_topic' => 'Lạc đề', 'unclear' => 'Chưa đủ để chấm'][$r['status']] ?? $r['status'] }}</div>
    @endif
    @if (! empty($r['scores']))
        <div class="d-flex flex-wrap gap-1 mb-2">
            @foreach ($r['scores'] as $k => $v)<span class="badge text-bg-light border">{{ $labels[$k] ?? $k }}: {{ rtrim(rtrim(number_format($v, 1), '0'), '.') }}</span>@endforeach
        </div>
    @endif
    @if ($r['type'] === 'speaking' && ! empty($r['transcript']))
        <div class="small text-muted mb-1">Máy chép lại bài nói ({{ $r['duration'] ?? 0 }}s): “{{ $r['transcript'] }}”</div>
    @endif
    @if ($r['type'] === 'paragraph' && $r['given'] !== '')
        <details class="small mb-1"><summary>Bài viết của bạn ({{ $r['word_count'] ?? '' }} {{ $lang === 'zh' ? 'chữ' : 'từ' }})</summary><div style="white-space: pre-wrap">{{ $r['given'] }}</div></details>
    @endif
    @if (! empty($r['strengths_vi']))<div class="small"><i class="bi bi-hand-thumbs-up text-success"></i> {{ implode(' ', $r['strengths_vi']) }}</div>@endif
    @if (! empty($r['feedback_vi']))<div class="small mt-1"><i class="bi bi-chat-left-text text-primary"></i> {{ $r['feedback_vi'] }}</div>@endif
    @if (! empty($r['next_step_vi']))<div class="small mt-1"><i class="bi bi-flag text-warning"></i> Luyện tiếp: {{ $r['next_step_vi'] }}</div>@endif
    @if (! empty($r['errors']))
        <ul class="small mt-1 mb-1">@foreach ($r['errors'] as $e)<li><s class="text-danger">{{ $e['original'] }}</s> → <strong class="text-success">{{ $e['correction'] }}</strong> <span class="text-muted">{{ $e['explanation_vi'] }}</span></li>@endforeach</ul>
    @endif
    @if (! empty($r['possible_pronunciation_issues']))
        <ul class="small mt-1 mb-1">@foreach ($r['possible_pronunciation_issues'] as $p)<li>Nghe như “{{ $p['heard_as'] }}” — có thể là <strong>{{ $p['likely_word'] }}</strong>: {{ $p['tip_vi'] }}</li>@endforeach</ul>
    @endif
    @if (! empty($r['corrected_text']))<details class="small"><summary>Bản đã sửa</summary><div style="white-space: pre-wrap">{{ $r['corrected_text'] }}</div></details>@endif
    @if (! empty($r['better_answer']))<details class="small"><summary>Câu trả lời tốt hơn từ ý của bạn</summary><div>{{ $r['better_answer'] }}</div></details>@endif
    @if (! empty($r['meta']['sample_answer']) || ! empty($r['sample_answer']))<details class="small"><summary>Bài mẫu</summary><div style="white-space: pre-wrap">{{ $r['meta']['sample_answer'] ?? $r['sample_answer'] }}</div></details>@endif
@endif
