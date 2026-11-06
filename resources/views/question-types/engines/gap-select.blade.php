@php
    $slot = function ($id) use ($c) {
        $html = '<span class="qt-gap"><span class="qt-gap-no">'.e($id).'</span><select class="qt-select" data-a="'.e($id).'"><option value="">—</option>';
        foreach ($c['choices'][$id] as $o) {
            $html .= '<option value="'.e($o['key']).'">'.($c['shared'] ? e($o['key']).'. ' : '').e($o['text']).'</option>';
        }

        return $html.'</select></span>';
    };
@endphp
@if ($c['shared'])
    <ol class="qt-option-list" type="A">
        @foreach (reset($c['choices']) as $o)<li><b>{{ $o['key'] }}.</b> {{ $o['text'] }}</li>@endforeach
    </ol>
@endif
@include('question-types.engines.gap-segments', ['c' => $c, 'slot' => $slot])
