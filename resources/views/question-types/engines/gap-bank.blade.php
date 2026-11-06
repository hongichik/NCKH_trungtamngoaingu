@php
    $slot = fn ($id) => '<span class="qt-slot" tabindex="0" role="button" data-gap="'.e($id).'"><span class="qt-gap-no">'.e($id).'</span><span class="qt-slot-text"></span><input type="hidden" data-a="'.e($id).'"></span>';
@endphp
<div class="qt-bankwrap" data-reuse="{{ ! empty($c['reuse']) ? 1 : 0 }}">
    <div class="qt-bank">
        @foreach ($c['bank'] as $w)<span class="qt-chip" draggable="true" role="button" tabindex="0">{{ $w }}</span>@endforeach
    </div>
    <div class="qt-hint mb-2">Kéo thẻ vào ô trống, hoặc bấm ô rồi bấm thẻ. Bấm vào ô đã điền để xoá.</div>
    @include('question-types.engines.gap-segments', ['c' => $c, 'slot' => $slot])
</div>
