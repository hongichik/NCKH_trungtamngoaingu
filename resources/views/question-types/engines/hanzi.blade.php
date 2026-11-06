<div class="qt-q qt-hanzi">
    <div class="qt-q-head">@if (! empty($c['pinyin']))<b>{{ $c['pinyin'] }}</b>@endif @if (! empty($c['meaning'])) — {{ $c['meaning'] }}@endif</div>
    <div class="qt-hanzi-row">
        @foreach ($c['chars'] as $i => $ch)
            <div class="qt-hanzi-cell">
                <div class="qt-hanzi-box" data-char="{{ $ch }}"></div>
                <div class="qt-hint qt-hanzi-state">Viết chữ {{ $i + 1 }}</div>
                <input type="hidden" data-a="c{{ $i }}">
            </div>
        @endforeach
    </div>
    <button type="button" class="qt-btn qt-btn-ghost qt-hanzi-demo">▶ Xem cách viết</button>
</div>
@once
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/hanzi-writer@3.7.2/dist/hanzi-writer.min.js"></script>
    @endpush
@endonce
