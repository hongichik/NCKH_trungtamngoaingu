@php use App\Support\QuestionTypes\Engine; @endphp
<div class="qt-hot">
    <div class="qt-hot-list">
        @foreach ($c['questions'] as $k => $q)
            <label class="qt-choice"><input type="radio" name="{{ $uid }}-hot" value="{{ $q['id'] }}" {{ $k === 0 ? 'checked' : '' }}> <span><span class="qt-badge">{{ $q['id'] }}</span> {{ $q['prompt'] }} <small class="qt-hot-state qt-hint">— chưa chọn</small></span></label>
            <input type="hidden" data-a="{{ $q['id'] }}" data-json="1">
        @endforeach
    </div>
    <div class="qt-figure qt-hot-img"><img src="{{ Engine::url($c['image']) }}" alt="" draggable="false"></div>
    <div class="qt-hint">Chọn câu ở trên rồi bấm vào vị trí trên ảnh.</div>
</div>
