@php use App\Support\QuestionTypes\Engine; @endphp
<div class="qt-q qt-speak" data-prep="{{ $c['prep_seconds'] }}" data-speak-max="{{ $c['speak_seconds'] }}">
    @if (! empty($c['prompt']))<div class="qt-prompt-box">{{ $c['prompt'] }}</div>@endif
    @if (! empty($c['image']))<div class="qt-figure"><img src="{{ Engine::url($c['image']) }}" alt=""></div>@endif
    @if (! empty($c['cue_card']))
        <div class="qt-cue"><div class="fw-semibold">{{ $c['cue_card']['topic'] }}</div><div class="qt-hint">You should say:</div><ul>@foreach ($c['cue_card']['points'] as $p)<li>{{ $p }}</li>@endforeach</ul></div>
    @endif
    @if (! empty($c['questions']))
        <ol class="qt-speak-questions">@foreach ($c['questions'] as $q)<li>{{ $q }}</li>@endforeach</ol>
    @endif
    @if (! empty($c['read_text']))
        <div class="qt-read">{{ $c['read_text'] }}</div>
        <button type="button" class="qt-btn qt-btn-ghost" data-speak="{{ base64_encode($c['read_text']) }}" data-b64="1">🔊 Nghe mẫu</button>
    @endif
    @if (! empty($c['repeat_b64']))
        <button type="button" class="qt-btn qt-btn-primary" data-speak="{{ $c['repeat_b64'] }}" data-b64="1">▶ Nghe đoạn cần nói lại</button>
    @endif
    <div class="qt-recorder">
        @if ($c['prep_seconds'])<button type="button" class="qt-btn qt-btn-ghost qt-prep">⏱ Chuẩn bị {{ $c['prep_seconds'] }} giây</button>@endif
        <button type="button" class="qt-btn qt-btn-danger qt-rec">● Ghi âm</button>
        <button type="button" class="qt-btn qt-btn-ghost qt-stop" hidden>■ Dừng (<span class="qt-sec">0</span>/{{ $c['speak_seconds'] }}s)</button>
        <audio class="qt-play" controls hidden></audio>
        <span class="qt-hint qt-rec-status">Thời gian nói tối đa {{ $c['speak_seconds'] }} giây.</span>
    </div>
    <input type="hidden" data-a="recorded" value="">
    <input type="hidden" data-a="seconds" value="0">
    <div class="qt-hint">Tiêu chí chấm: {{ implode(' · ', $c['criteria']) }}</div>
</div>
