@php
    $max = $c['limits']['max_words'] ?? 0;
    $slot = fn ($id) => '<span class="qt-gap"><span class="qt-gap-no">'.e($id).'</span><input type="text" class="qt-input" autocomplete="off" spellcheck="false" data-a="'.e($id).'"'
        .($max ? ' title="Tối đa '.$max.' từ"' : '').'></span>';
@endphp
@if ($max)<div class="qt-hint mb-2">Mỗi ô tối đa {{ $max }} từ{{ ($c['limits']['numbers_free'] ?? false) ? ' (con số không tính)' : '' }}.</div>@endif
@include('question-types.engines.gap-segments', ['c' => $c, 'slot' => $slot])
