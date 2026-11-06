<div class="qt-cat">
    @foreach ($c['cards'] as $id => $text)
        <div class="qt-cat-row">
            <span class="qt-cat-card">{{ $text }}</span>
            <span class="qt-seg">
                @foreach ($c['group_names'] as $g)
                    <label><input type="radio" name="{{ $uid }}-{{ $id }}" value="{{ $g }}" data-a="{{ $id }}"><span>{{ $g }}</span></label>
                @endforeach
            </span>
        </div>
    @endforeach
</div>
