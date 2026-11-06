@foreach ($c['questions'] as $q)
    <div class="qt-q">
        <div class="qt-q-head"><span class="qt-badge">{{ $q['id'] }}</span> <span class="qt-prompt">{{ $q['prompt'] }}</span></div>
        @if (! empty($q['keyword']))<div class="qt-hint">Từ khoá bắt buộc: <b class="qt-keyword">{{ mb_strtoupper($q['keyword']) }}</b></div>@endif
        <input type="text" class="qt-input qt-input-wide" autocomplete="off" data-a="{{ $q['id'] }}" value="{{ $q['start'] ?? '' }}"
               @if (! empty($q['max_words']) || $t->option('max_words')) placeholder="Tối đa {{ $q['max_words'] ?? $t->option('max_words') }} từ" @endif>
    </div>
@endforeach
