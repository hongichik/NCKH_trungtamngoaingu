@extends($layout)

@section('title', 'Xem trước: '.$exercise->title)

@section('content')
    <div class="alert alert-info">Đây là giao diện học viên thấy. <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="fill-all">Điền đáp án đúng</button></div>
    @if ($exercise->description)<p>{{ $exercise->description }}</p>@endif
    @include('custom.partials.items', ['exercise' => $exercise, 'prefixUid' => 'pv-', 'audioUrl' => fn ($item) => $item->audio_path ? route($prefix.'.items.audio-file', [$course, $exercise, $item]) : null, 'imageUrl' => fn ($item) => $item->image_path ? route($prefix.'.items.image-file', [$course, $exercise, $item]) : null])
@endsection

@push('scripts')
<script>
const perfect = @json($exercise->items->mapWithKeys(fn ($i) => [$i->id => $i->type()?->engine()->perfectAnswer($i->content, $i->type())]));
document.getElementById('fill-all').addEventListener('click', () => Object.entries(perfect).forEach(([id, a]) => QT.fill(document.getElementById('pv-' + id), a)));
</script>
@endpush
