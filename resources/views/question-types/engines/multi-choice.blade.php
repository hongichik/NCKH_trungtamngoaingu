@foreach ($c['questions'] as $q)
    <div class="qt-q" data-choose="{{ $q['choose'] }}">
        <div class="qt-q-head"><span class="qt-badge">{{ $q['id'] }}</span> {{ $q['prompt'] ?? '' }} <span class="qt-hint">(chọn {{ $q['choose'] }} đáp án)</span></div>
        <div class="qt-choices">
            @foreach ($q['choices'] as $o)
                <label class="qt-choice"><input type="checkbox" value="{{ $o['key'] }}" data-a="{{ $q['id'] }}"> <span><b>{{ $o['key'] }}.</b> {{ $o['text'] }}</span></label>
            @endforeach
        </div>
    </div>
@endforeach
