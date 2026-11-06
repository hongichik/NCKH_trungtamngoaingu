{{-- Ngữ liệu chung: tiêu đề, bài đọc, ảnh (có điểm đánh dấu), file nghe, lời thoại cho máy đọc. Biến: $s --}}
@php use App\Support\QuestionTypes\Engine; @endphp
@if ($s)
    <div class="qt-stimulus">
        @if (! empty($s['title']))<div class="qt-stimulus-title">{{ $s['title'] }}</div>@endif
        @if (! empty($s['image']))
            <div class="qt-figure">
                <img src="{{ Engine::url($s['image']) }}" alt="">
                @foreach ($s['markers'] ?? [] as $m)
                    <span class="qt-marker" style="left: {{ $m['x'] }}%; top: {{ $m['y'] }}%">{{ $m['key'] }}</span>
                @endforeach
            </div>
        @endif
        @if (! empty($s['audio']))
            <audio class="w-100 my-2" controls controlsList="nodownload" preload="metadata" src="{{ Engine::url($s['audio']) }}"></audio>
        @elseif (! empty($s['transcript']))
            @include('question-types.speak-button', ['text' => $s['transcript'], 'label' => 'Nghe bài'])
        @endif
        @if (! empty($s['passage']))<div class="qt-passage">{{ $s['passage'] }}</div>@endif
    </div>
@endif
