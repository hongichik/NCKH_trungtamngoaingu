@if (! empty($c['prompt']))<div class="qt-q-head">{{ $c['prompt'] }}</div>@endif
<div class="qt-order">
    <ol class="qt-order-list">
        @foreach ($c['cards'] as $idx => $text)
            <li class="qt-order-item" data-idx="{{ $idx }}" draggable="true">
                <span class="qt-order-text">{{ $text }}</span>
                <span class="qt-order-btns"><button type="button" class="qt-btn qt-btn-ghost" data-move="-1" aria-label="Lên">▲</button><button type="button" class="qt-btn qt-btn-ghost" data-move="1" aria-label="Xuống">▼</button></span>
            </li>
        @endforeach
    </ol>
    <input type="hidden" data-a="order" data-json="1">
</div>
