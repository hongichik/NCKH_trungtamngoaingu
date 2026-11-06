@php use App\Support\QuestionTypes\Engine; $fixed = (bool) $t->option('choices'); @endphp
@foreach ($c['questions'] as $q)
    {{-- Chữ to cho đáp án ngắn là chữ Hán / Nhật / Hàn (你, 好…); đáp án Latin ngắn ("Do", "is") vẫn cỡ thường cho đồng đều --}}
    @php $compact = collect($q['choices'])->every(fn ($o) => mb_strlen($o['text']) <= 4 && preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $o['text'])); @endphp
    <div class="qt-q">
        <div class="qt-q-head"><span class="qt-badge">{{ $q['id'] }}</span> <span class="qt-prompt {{ mb_strlen($q['prompt'] ?? '') <= 4 ? 'qt-big' : '' }}">{{ $q['prompt'] ?? '' }}</span></div>
        @if (! empty($q['image']))<img class="qt-q-img" src="{{ Engine::url($q['image']) }}" alt="">@endif
        <div class="{{ $compact || $fixed ? 'qt-choices-row' : 'qt-choices' }}">
            @foreach ($q['choices'] as $o)
                <label class="qt-choice {{ $compact && ! $fixed ? 'qt-choice-big' : '' }}">
                    <input type="radio" name="{{ $uid }}-{{ $q['id'] }}" value="{{ $o['key'] }}" data-a="{{ $q['id'] }}">
                    <span>@unless ($fixed)<b>{{ $o['key'] }}.</b> @endunless{{ $o['text'] }}</span>
                </label>
            @endforeach
        </div>
    </div>
@endforeach
