{{-- Văn bản có ô trống; dòng chứa "|" hiển thị thành bảng. Biến: $c (có segments), $slot (closure id => html) --}}
@php
    // Ghép lại thành từng dòng để nhận diện bảng.
    $lines = [[]];
    foreach ($c['segments'] as $seg) {
        if (isset($seg['t'])) {
            $chunks = explode("\n", $seg['t']);
            foreach ($chunks as $k => $chunk) {
                if ($k > 0) { $lines[] = []; }
                if ($chunk !== '') { $lines[count($lines) - 1][] = ['t' => $chunk]; }
            }
        } else {
            $lines[count($lines) - 1][] = $seg;
        }
    }
    $isRow = fn ($line) => collect($line)->contains(fn ($s) => isset($s['t']) && str_contains($s['t'], '|'));
@endphp
<div class="qt-gaptext">
    @php $i = 0; $n = count($lines); @endphp
    @while ($i < $n)
        @if ($isRow($lines[$i]))
            <table class="qt-table">
                @php $first = true; @endphp
                @while ($i < $n && $isRow($lines[$i]))
                    @php
                        // Tách dòng thành các ô theo "|".
                        $cells = [[]];
                        foreach ($lines[$i] as $s) {
                            if (isset($s['t'])) {
                                foreach (explode('|', $s['t']) as $k => $piece) {
                                    if ($k > 0) { $cells[] = []; }
                                    if (trim($piece) !== '') { $cells[count($cells) - 1][] = ['t' => trim($piece)]; }
                                }
                            } else { $cells[count($cells) - 1][] = $s; }
                        }
                    @endphp
                    <tr>
                        @foreach ($cells as $cell)
                            @php $tag = $first ? 'th' : 'td'; @endphp
                            <{{ $tag }}>@foreach ($cell as $s)@if (isset($s['t'])){{ $s['t'] }} @else{!! $slot($s['gap']) !!}@endif @endforeach</{{ $tag }}>
                        @endforeach
                    </tr>
                    @php $first = false; $i++; @endphp
                @endwhile
            </table>
        @else
            <div class="qt-line">@foreach ($lines[$i] as $s)@if (isset($s['t'])){{ $s['t'] }}@else{!! $slot($s['gap']) !!}@endif @endforeach&nbsp;</div>
            @php $i++; @endphp
        @endif
    @endwhile
</div>
