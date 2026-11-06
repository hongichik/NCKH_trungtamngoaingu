<div class="qt-q qt-hl">
    <div class="qt-q-head">{{ $c['prompt'] }}</div>
    <div class="qt-tokens qt-passage">@foreach ($c['tokens'] as $i => $tok)<button type="button" class="qt-tok" data-i="{{ $i }}">{{ $tok }}</button>{{ $c['joiner'] }}@endforeach</div>
    <input type="hidden" data-a="selected" data-json="1" value="[]">
</div>
