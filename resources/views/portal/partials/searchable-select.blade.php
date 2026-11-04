@php
    $currentValue = $currentValue ?? '';
    $required = $required ?? false;
@endphp
<div class="searchable-select" data-role="{{ $role }}" @if(isset($dependsOn)) data-depends-on="{{ $dependsOn }}" @endif>
    <input type="text" class="form-control searchable-select-input @error($name) is-invalid @enderror" placeholder="{{ $placeholder }}" autocomplete="off" value="{{ old($name, $currentValue) }}">
    <input type="hidden" name="{{ $name }}" class="searchable-select-value" value="{{ old($name, $currentValue) }}">
    <div class="searchable-select-menu"></div>
    @error($name) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>
