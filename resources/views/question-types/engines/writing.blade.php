<div class="qt-q qt-writing" data-lang="{{ $lang }}" data-min="{{ $c['min_words'] ?? 0 }}" data-max="{{ $c['max_words'] ?? 0 }}" data-minutes="{{ $c['time_minutes'] ?? 0 }}">
    <div class="qt-prompt-box">{{ $c['prompt'] }}</div>
    @if (! empty($c['keywords']))<div class="qt-hint">Phải dùng: @foreach ($c['keywords'] as $k)<span class="qt-keyword">{{ $k }}</span> @endforeach</div>@endif
    @if (! empty($c['ideas']))
        <details class="qt-details"><summary>Gợi ý ý tưởng</summary><ul>@foreach ($c['ideas'] as $i)<li>{{ $i }}</li>@endforeach</ul></details>
    @endif
    @if (! empty($c['useful_language']))
        <details class="qt-details"><summary>Cụm từ hữu ích</summary><ul>@foreach ($c['useful_language'] as $i)<li>{{ $i }}</li>@endforeach</ul></details>
    @endif
    <textarea class="qt-input qt-input-wide" rows="10" data-a="text" placeholder="Viết bài của bạn ở đây…"></textarea>
    <div class="qt-hint"><span class="qt-wc">0</span> {{ $lang === 'zh' ? 'chữ' : 'từ' }}@if (! empty($c['min_words'])) · yêu cầu {{ $c['min_words'] }}{{ ! empty($c['max_words']) ? '–'.$c['max_words'] : '+' }}@endif
        @if (! empty($c['time_minutes'])) · <span class="qt-timer">{{ $c['time_minutes'] }}:00</span>@endif</div>
    <div class="qt-hint">Tiêu chí chấm: {{ implode(' · ', $c['criteria']) }}</div>
</div>
