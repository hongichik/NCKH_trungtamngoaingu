{{-- Các phần của bài tập tuỳ chỉnh. Biến: $exercise (đã load items), $audioUrl, $imageUrl (closure item => ?url), $prefixUid --}}
@foreach ($exercise->items as $item)
    @php $t = $item->type(); @endphp
    @if ($t)
        <div class="card border-0 shadow-sm mb-3 custom-item" data-item="{{ $item->id }}" data-engine="{{ $t->engine }}">
            <div class="card-header bg-white fw-semibold">Phần {{ $loop->iteration }}{{ $item->title ? ': '.$item->title : '' }} <span class="small text-muted fw-normal">· {{ $t->name }}</span></div>
            <div class="card-body">
                @include('question-types.render', ['type' => $t, 'content' => $item->contentWithAudio($audioUrl($item), isset($imageUrl) ? $imageUrl($item) : null), 'uid' => $prefixUid.$item->id, 'seed' => 'item'.$item->id])
            </div>
        </div>
    @endif
@endforeach
