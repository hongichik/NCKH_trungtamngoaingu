{{--
    Image picker with drag & drop, preview and optional crop.
    @include('admin.partials.image-field', [
        'name' => 'image',              // file input name
        'label' => 'Ảnh đại diện',
        'currentUrl' => $url ?? null,   // existing image
        'aspect' => 16/9,               // crop ratio; null = free crop
        'removeName' => 'remove_image', // optional "delete current image" checkbox name
        'hint' => '...',
    ])
--}}
@php
    $fieldId = 'imgf-'.$name.'-'.Str::random(4);
    $aspect = $aspect ?? null;
@endphp
<div class="image-field mb-3" data-aspect="{{ $aspect ?? '' }}">
    @if (! empty($label))
        <label class="form-label">{{ $label }}</label>
    @endif

    <div class="image-field-drop" tabindex="0" role="button" aria-label="Chọn ảnh">
        <img class="image-field-preview {{ ! empty($currentUrl) ? '' : 'd-none' }}" src="{{ $currentUrl ?? '' }}" alt="">
        <div class="image-field-placeholder {{ ! empty($currentUrl) ? 'd-none' : '' }}">
            <i class="ti ti-photo-up fs-1 d-block mb-1"></i>
            Kéo thả ảnh vào đây hoặc bấm để chọn
        </div>
    </div>
    <input type="file" name="{{ $name }}" id="{{ $fieldId }}" class="d-none image-field-input" accept="image/jpeg,image/png,image/webp,image/gif">

    <div class="d-flex flex-wrap gap-2 mt-2">
        <button type="button" class="btn btn-sm btn-outline-primary image-field-pick"><i class="ti ti-upload me-1"></i>{{ ! empty($currentUrl) ? 'Đổi ảnh' : 'Chọn ảnh' }}</button>
        <button type="button" class="btn btn-sm btn-outline-secondary image-field-recrop d-none"><i class="ti ti-crop me-1"></i>Cắt lại</button>
        @if (! empty($removeName) && ! empty($currentUrl))
            <label class="form-check form-check-inline mb-0 ms-auto align-self-center">
                <input type="checkbox" name="{{ $removeName }}" value="1" class="form-check-input image-field-remove">
                <span class="form-check-label small">Xoá ảnh hiện tại</span>
            </label>
        @endif
    </div>
    @if (! empty($hint))
        <div class="form-hint">{{ $hint }}</div>
    @endif
    @error($name) <div class="text-danger small mt-1">{{ $message }}</div> @enderror
</div>

@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('vendor/cropperjs/cropper.min.css') }}">
        <style>
            .image-field-drop {
                position: relative; display: flex; align-items: center; justify-content: center;
                min-height: 140px; border: 2px dashed var(--tblr-border-color); border-radius: .5rem;
                background: var(--tblr-bg-surface-secondary, #f6f8fb); cursor: pointer; overflow: hidden; text-align: center;
            }
            .image-field-drop.is-over { border-color: var(--tblr-primary); background: rgba(10, 74, 117, .05); }
            .image-field-preview { display: block; max-width: 100%; max-height: 260px; }
            .image-field-placeholder { color: var(--tblr-secondary); font-size: .85rem; padding: 1rem; }
            #image-crop-modal .crop-area { max-height: 65vh; background: #222; }
            #image-crop-modal .crop-area img { display: block; max-width: 100%; }
        </style>
    @endpush
    @push('scripts')
        <div class="modal modal-blur fade" id="image-crop-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Cắt &amp; chỉnh ảnh</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="crop-area"><img alt=""></div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <div class="btn-group flex-wrap" role="group">
                            <button type="button" class="btn btn-outline-secondary" data-crop="rotate-left" title="Xoay trái"><i class="ti ti-rotate-2"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-crop="rotate-right" title="Xoay phải"><i class="ti ti-rotate-clockwise-2"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-crop="flip" title="Lật ngang"><i class="ti ti-flip-vertical"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-crop="zoom-in" title="Phóng to"><i class="ti ti-zoom-in"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-crop="zoom-out" title="Thu nhỏ"><i class="ti ti-zoom-out"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-crop="free" title="Cắt tự do / theo tỉ lệ"><i class="ti ti-aspect-ratio"></i> <span class="crop-ratio-label"></span></button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-link" data-crop="original">Dùng ảnh gốc</button>
                            <button type="button" class="btn btn-primary" data-crop="apply"><i class="ti ti-check me-1"></i>Cắt &amp; dùng ảnh này</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="{{ asset('vendor/cropperjs/cropper.min.js') }}"></script>
        <script>
        (function () {
            const modalEl = document.getElementById('image-crop-modal');
            // The admin's Tabler bundle exposes Bootstrap as window.tabler, not window.bootstrap.
            const BS = window.bootstrap || window.tabler;
            const modal = new BS.Modal(modalEl);
            const cropImg = modalEl.querySelector('.crop-area img');
            const ratioLabel = modalEl.querySelector('.crop-ratio-label');
            let cropper = null, active = null, scaleX = 1, applied = false;

            function setFile(field, file) {
                const dt = new DataTransfer();
                dt.items.add(file);
                field.input.files = dt.files;
                field.preview.src = URL.createObjectURL(file);
                field.preview.classList.remove('d-none');
                field.placeholder.classList.add('d-none');
                field.recrop.classList.toggle('d-none', !field.original);
                if (field.remove) field.remove.checked = false;
            }

            function openCropper(field) {
                if (!field.original || field.original.type === 'image/gif') {
                    // Cropping would flatten GIF animation; keep it as is.
                    setFile(field, field.original);
                    return;
                }
                active = field;
                applied = false;
                scaleX = 1;
                cropImg.src = URL.createObjectURL(field.original);
                modal.show();
            }

            modalEl.addEventListener('shown.bs.modal', () => {
                const ratio = parseFloat(active.root.dataset.aspect) || NaN;
                active.ratio = ratio;
                ratioLabel.textContent = isNaN(ratio) ? 'Tự do' : 'Theo tỉ lệ';
                cropper = new Cropper(cropImg, { viewMode: 1, autoCropArea: 1, aspectRatio: ratio, background: false });
            });
            modalEl.addEventListener('hidden.bs.modal', () => {
                if (cropper) { cropper.destroy(); cropper = null; }
                // Closing without choosing keeps the untouched original.
                if (active && !applied) setFile(active, active.original);
                active = null;
            });

            modalEl.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-crop]');
                if (!btn || !cropper) return;
                switch (btn.dataset.crop) {
                    case 'rotate-left': cropper.rotate(-90); break;
                    case 'rotate-right': cropper.rotate(90); break;
                    case 'flip': scaleX = -scaleX; cropper.scaleX(scaleX); break;
                    case 'zoom-in': cropper.zoom(0.1); break;
                    case 'zoom-out': cropper.zoom(-0.1); break;
                    case 'free': {
                        const free = !isNaN(cropper.options.aspectRatio);
                        cropper.setAspectRatio(free ? NaN : active.ratio);
                        ratioLabel.textContent = free || isNaN(active.ratio) ? 'Tự do' : 'Theo tỉ lệ';
                        break;
                    }
                    case 'original': applied = true; setFile(active, active.original); modal.hide(); break;
                    case 'apply': {
                        const type = active.original.type === 'image/png' ? 'image/png' : 'image/jpeg';
                        const canvas = cropper.getCroppedCanvas({ maxWidth: 2400, maxHeight: 2400, imageSmoothingQuality: 'high', fillColor: type === 'image/jpeg' ? '#fff' : 'transparent' });
                        const field = active;
                        canvas.toBlob((blob) => {
                            const name = field.original.name.replace(/\.[^.]+$/, '') + (type === 'image/png' ? '.png' : '.jpg');
                            setFile(field, new File([blob], name, { type }));
                        }, type, 0.92);
                        applied = true;
                        modal.hide();
                        break;
                    }
                }
            });

            document.querySelectorAll('.image-field').forEach((root) => {
                const field = {
                    root,
                    input: root.querySelector('.image-field-input'),
                    preview: root.querySelector('.image-field-preview'),
                    placeholder: root.querySelector('.image-field-placeholder'),
                    recrop: root.querySelector('.image-field-recrop'),
                    remove: root.querySelector('.image-field-remove'),
                    drop: root.querySelector('.image-field-drop'),
                    original: null,
                };
                const pick = () => field.input.click();
                const accept = (file) => {
                    if (!file || !file.type.startsWith('image/')) return;
                    field.original = file;
                    openCropper(field);
                };

                root.querySelector('.image-field-pick').addEventListener('click', pick);
                field.drop.addEventListener('click', pick);
                field.drop.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(); } });
                field.recrop.addEventListener('click', () => openCropper(field));
                // Assigning input.files from script doesn't fire "change", so this only sees real picks.
                field.input.addEventListener('change', () => accept(field.input.files[0]));
                ['dragenter', 'dragover'].forEach((ev) => field.drop.addEventListener(ev, (e) => { e.preventDefault(); field.drop.classList.add('is-over'); }));
                ['dragleave', 'drop'].forEach((ev) => field.drop.addEventListener(ev, (e) => { e.preventDefault(); field.drop.classList.remove('is-over'); }));
                field.drop.addEventListener('drop', (e) => accept(e.dataTransfer.files[0]));
            });
        })();
        </script>
    @endpush
@endonce
