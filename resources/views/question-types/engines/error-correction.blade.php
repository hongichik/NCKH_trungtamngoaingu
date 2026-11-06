@foreach ($c['sentences'] as $s)
    <div class="qt-q qt-err">
        <div class="qt-q-head"><span class="qt-badge">{{ $s['id'] }}</span></div>
        <div class="qt-tokens">@foreach ($s['tokens'] as $i => $tok)<button type="button" class="qt-tok" data-i="{{ $i }}">{{ $tok }}</button>{{ $c['joiner'] }}@endforeach</div>
        @if ($c['need_fix'])
            <input type="text" class="qt-input qt-err-fix" placeholder="Sửa thành…" autocomplete="off" disabled>
        @endif
        <input type="hidden" data-a="{{ $s['id'] }}" data-json="1">
    </div>
@endforeach
