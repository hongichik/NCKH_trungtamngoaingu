<div class="qt-match">
    @unless (collect($c['choices'])->every(fn ($o) => $o['key'] === $o['text']))
        <div class="qt-option-box">
            @if (! empty($c['options_title']))<div class="qt-option-title">{{ $c['options_title'] }}</div>@endif
            <ul class="qt-option-list">
                @foreach ($c['choices'] as $o)<li><b>{{ $o['key'] }}</b> &nbsp;<span class="{{ mb_strlen($o['text']) <= 4 ? 'qt-big' : '' }}">{{ $o['text'] }}</span></li>@endforeach
            </ul>
            @if ($c['reuse'])<div class="qt-hint">Một lựa chọn có thể dùng nhiều lần.</div>@endif
        </div>
    @endunless
    @foreach ($c['items'] as $item)
        <div class="qt-match-row">
            <span class="qt-badge">{{ $item['id'] }}</span>
            <span class="qt-match-text {{ mb_strlen($item['text']) <= 2 ? 'qt-big' : '' }}">{{ $item['text'] }}</span>
            <select class="qt-select" data-a="{{ $item['id'] }}">
                <option value="">—</option>
                @foreach ($c['choices'] as $o)<option value="{{ $o['key'] }}">{{ $o['key'] }}</option>@endforeach
            </select>
        </div>
    @endforeach
</div>
