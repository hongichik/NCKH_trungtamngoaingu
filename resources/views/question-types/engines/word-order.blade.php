@foreach ($c['questions'] as $q)
    <div class="qt-q qt-words" data-joiner="{{ $c['joiner'] }}">
        <div class="qt-q-head"><span class="qt-badge">{{ $q['id'] }}</span> {{ $q['prompt'] ?? '' }}</div>
        <div class="qt-built" aria-live="polite"></div>
        <div class="qt-bank">
            @foreach ($q['cards'] as $w)<button type="button" class="qt-chip">{{ $w }}</button>@endforeach
            <button type="button" class="qt-btn qt-btn-ghost qt-words-reset">↺ Làm lại</button>
        </div>
        <input type="hidden" data-a="{{ $q['id'] }}">
    </div>
@endforeach
