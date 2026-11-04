@php
    $currentUrl = $currentUrl ?? null;
    $required = $required ?? false;
@endphp
<div class="mb-2">
    <label class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>

    <div class="image-drop-zone" data-aspect="{{ $aspect }}" data-target="{{ $name }}-input" data-name="{{ $name }}" style="aspect-ratio: {{ $aspect }};">
        <div class="image-drop-preview {{ $currentUrl ? '' : 'd-none' }}">
            <img src="{{ $currentUrl }}" alt="">
        </div>
        <div class="image-drop-placeholder {{ $currentUrl ? 'd-none' : '' }}">
            <i class="bi bi-cloud-arrow-up-fill"></i>
            <div class="small mt-1">Kéo thả ảnh vào đây<br>hoặc bấm để chọn ảnh</div>
        </div>
        <div class="image-drop-overlay">
            <i class="bi bi-arrow-repeat"></i> Đổi ảnh
        </div>
    </div>
    <input type="file" id="{{ $name }}-input" name="{{ $name }}" accept="image/*" class="d-none image-drop-input">

    @error($name) <div class="text-danger small mt-1">{{ $message }}</div> @enderror
</div>
