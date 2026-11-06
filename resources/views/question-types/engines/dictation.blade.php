@php use App\Support\QuestionTypes\Engine; @endphp
@foreach ($c['questions'] as $q)
    <div class="qt-q">
        <div class="qt-q-head"><span class="qt-badge">{{ $q['id'] }}</span>
            @if (! empty($q['audio']))
                <audio controls controlsList="nodownload" preload="metadata" src="{{ Engine::url($q['audio']) }}"></audio>
            @else
                <button type="button" class="qt-btn qt-btn-primary" data-speak="{{ $q['speak'] }}" data-b64="1">▶ Nghe</button>
                <button type="button" class="qt-btn qt-btn-ghost" data-speak="{{ $q['speak'] }}" data-b64="1" data-rate="0.6">▶ Nghe chậm</button>
            @endif
        </div>
        <textarea class="qt-input qt-input-wide" rows="2" autocomplete="off" spellcheck="false" data-a="{{ $q['id'] }}" placeholder="Gõ lại câu bạn nghe được"></textarea>
    </div>
@endforeach
