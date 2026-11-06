{{-- Một câu hỏi khách quan: $q (type, prompt, options), $name (tên ô), $given (đã trả lời), $disabled --}}
@php $disabled = $disabled ?? false; $given = $given ?? ''; @endphp
<div class="fw-semibold mb-2" style="white-space: pre-wrap">{{ $q['prompt'] }}</div>
@if (in_array($q['type'], ['mcq', 'cloze'], true) && ! empty($q['options']))
    @foreach ($q['options'] as $o)
        @php $letter = \App\Support\Exercise\Answers::letter($o); @endphp
        <label class="form-check">
            <input class="form-check-input" type="radio" name="{{ $name }}" value="{{ $letter }}" @checked($given !== '' && \App\Support\Exercise\Answers::letter($given) === $letter) @disabled($disabled)>
            <span class="form-check-label">{{ $o }}</span>
        </label>
    @endforeach
@elseif ($q['type'] === 'true_false')
    <div class="d-flex gap-3">
        @foreach (['True' => 'Đúng', 'False' => 'Sai'] as $v => $label)
            <label class="form-check"><input class="form-check-input" type="radio" name="{{ $name }}" value="{{ $v }}" @checked(strcasecmp($given, $v) === 0) @disabled($disabled)><span class="form-check-label">{{ $label }}</span></label>
        @endforeach
    </div>
@elseif ($q['type'] === 'ordering')
    @foreach ($q['options'] ?? [] as $o)<div class="small">{{ $o }}</div>@endforeach
    <input class="form-control mt-1" name="{{ $name }}" value="{{ $given }}" placeholder="Thứ tự, vd: c-a-b-d" @disabled($disabled)>
@else
    <input class="form-control" name="{{ $name }}" value="{{ $given }}" placeholder="Điền đáp án…" autocomplete="off" @disabled($disabled)>
@endif
