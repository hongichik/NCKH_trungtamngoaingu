{{--
    Hiển thị một bài theo dạng của thư viện (không kèm đáp án).
    Biến: $type (QuestionType), $content (nội dung gốc), $uid (mã duy nhất trên trang), $seed (tuỳ chọn, để xáo trộn ổn định)
    Câu trả lời được thu thập bằng QT.collect(phần tử .qt) trong public/js/qtypes.js.
--}}
@php
    use App\Support\QuestionTypes\Engine;
    $engine = $type->engine();
    $c = $engine->prepare($content, $type, $seed ?? $type->code);
    $lang = $type->langOf($content);
    $speech = $lang === 'zh' ? 'zh-CN' : 'en-US';
@endphp
<div class="qt qt-engine-{{ str_replace('_', '-', $type->engine) }}" id="{{ $uid }}" data-engine="{{ $type->engine }}" data-speech="{{ $speech }}" lang="{{ $lang === 'zh' ? 'zh' : 'en' }}">
    @if (! empty($c['instructions']))
        <div class="qt-instructions">{{ $c['instructions'] }}</div>
    @endif
    @include('question-types.stimulus', ['s' => $c['stimulus'] ?? []])
    @include($engine->view(), ['c' => $c, 't' => $type, 'uid' => $uid, 'lang' => $lang])
</div>
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/qtypes.css') }}?v=1">
    @endpush
    @push('scripts')
        <script src="{{ asset('js/speech.js') }}?v=2"></script>
        <script src="{{ asset('js/qtypes.js') }}?v=1"></script>
    @endpush
@endonce
