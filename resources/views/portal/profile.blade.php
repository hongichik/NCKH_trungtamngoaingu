@extends('layouts.portal')

@section('title', 'Thông tin thí sinh')

@section('content')
    <h1 class="h4 mb-4">Thông tin thí sinh</h1>

    <form method="POST" action="{{ route('portal.profile.update') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">I. Thông tin cá nhân (bắt buộc cho mọi hình thức đăng ký)</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name', $student->last_name) }}" required>
                        @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Giới tính <span class="text-danger">*</span></label>
                        <select name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                            <option value="">Chọn...</option>
                            @foreach (['Nam', 'Nữ', 'Khác'] as $option)
                                <option value="{{ $option }}" @selected(old('gender', $student->gender) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('gender') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ngày sinh <span class="text-danger">*</span></label>
                        <input type="date" name="dob" class="form-control @error('dob') is-invalid @enderror" value="{{ old('dob', optional($student->dob)->format('Y-m-d')) }}" required>
                        @error('dob') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $student->email) }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $student->phone) }}" required>
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">II. Giấy tờ tuỳ thân</h2>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Số CCCD/Hộ chiếu <span class="text-danger">*</span></label>
                        <input type="text" name="identity_number" class="form-control @error('identity_number') is-invalid @enderror" value="{{ old('identity_number', $student->identity_number) }}" required>
                        @error('identity_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ngày cấp <span class="text-danger">*</span></label>
                        <input type="date" name="identity_date" class="form-control @error('identity_date') is-invalid @enderror" value="{{ old('identity_date', optional($student->identity_date)->format('Y-m-d')) }}" required>
                        @error('identity_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        @php
                            $identityPlaceOptions = \App\Models\Student::IDENTITY_PLACE_OPTIONS;
                            $currentIdentityPlace = old('identity_place', $student->identity_place);
                        @endphp
                        <label class="form-label">Nơi cấp <span class="text-danger">*</span></label>
                        <select name="identity_place" class="form-select @error('identity_place') is-invalid @enderror" required>
                            <option value="">Chọn nơi cấp (ghi trên mặt sau CCCD)...</option>
                            @foreach ($identityPlaceOptions as $option)
                                <option value="{{ $option }}" @selected($currentIdentityPlace === $option)>{{ $option }}</option>
                            @endforeach
                            @if ($currentIdentityPlace && !in_array($currentIdentityPlace, $identityPlaceOptions))
                                <option value="{{ $currentIdentityPlace }}" selected>{{ $currentIdentityPlace }}</option>
                            @endif
                        </select>
                        <small class="form-text text-muted">"Cục Cảnh sát QLHC về TTXH" áp dụng cho CCCD gắn chip (từ 10/10/2018 đến nay).</small>
                        @error('identity_place') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-12">
                        <div class="small fw-semibold mb-2">Lưu ý ảnh chân dung:</div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="sample-photo valid">
                            <img src="{{ asset('images/portal/anh-chan-dung-hop-le.jpg') }}" alt="Ảnh chân dung hợp lệ" class="img-fluid rounded">
                            <div class="sample-tag valid"><i class="bi bi-check-circle-fill"></i> Hợp lệ</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="sample-photo invalid">
                            <img src="{{ asset('images/portal/anh-chan-dung-khong-hop-le.jpg') }}" alt="Ảnh chân dung không hợp lệ" class="img-fluid rounded">
                            <div class="sample-tag invalid"><i class="bi bi-x-circle-fill"></i> Không hợp lệ</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <p class="small text-success mb-1"><i class="bi bi-check-circle me-1"></i>Ảnh nền trắng, rõ mặt, không đeo kính.</p>
                        <p class="small text-danger mb-0"><i class="bi bi-x-circle me-1"></i>Không dùng ảnh nền màu, ảnh tự sướng, đeo kính hoặc có vật/thú cưng khác trong ảnh.</p>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3">
                        @include('portal.partials.image-upload', [
                            'name' => 'avatar',
                            'label' => 'Ảnh chân dung',
                            'currentUrl' => $student->avatar ? Storage::url($student->avatar) : null,
                            'aspect' => '0.75',
                        ])
                    </div>
                </div>

                <div class="row g-3 g-lg-4">
                    <div class="col-md-6">
                        @include('portal.partials.image-upload', [
                            'name' => 'identity_front_image',
                            'label' => 'Ảnh CCCD mặt trước',
                            'currentUrl' => $student->identity_front_image ? Storage::url($student->identity_front_image) : null,
                            'aspect' => '1.586',
                            'required' => true,
                        ])
                    </div>
                    <div class="col-md-6">
                        @include('portal.partials.image-upload', [
                            'name' => 'identity_back_image',
                            'label' => 'Ảnh CCCD mặt sau',
                            'currentUrl' => $student->identity_back_image ? Storage::url($student->identity_back_image) : null,
                            'aspect' => '1.586',
                            'required' => true,
                        ])
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h6 mb-1">III. Địa chỉ thường trú</h2>
                <p class="text-muted small mb-3">Theo đơn vị hành chính 2 cấp (Tỉnh/Thành phố – Xã/Phường) áp dụng từ 01/07/2025.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tỉnh/Thành phố <span class="text-danger">*</span></label>
                        @include('portal.partials.searchable-select', [
                            'role' => 'province',
                            'name' => 'address_province',
                            'placeholder' => 'Gõ để tìm tỉnh/thành phố...',
                            'currentValue' => $student->address_province,
                            'required' => true,
                        ])
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Xã/Phường <span class="text-danger">*</span></label>
                        @include('portal.partials.searchable-select', [
                            'role' => 'ward',
                            'dependsOn' => 'province',
                            'name' => 'address_ward',
                            'placeholder' => 'Chọn tỉnh/thành phố trước...',
                            'currentValue' => $student->address_ward,
                            'required' => true,
                        ])
                    </div>
                    <div class="col-12">
                        <label class="form-label">Địa chỉ chi tiết</label>
                        <input type="text" name="address_detail" class="form-control" value="{{ old('address_detail', $student->address_detail) }}" placeholder="Số nhà, đường/thôn xóm...">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 hsk-block">
            <div class="card-body">
                <h2 class="h6 mb-1 text-accent">IV. Thông tin dành riêng cho thí sinh đăng ký thi HSK</h2>
                <p class="text-muted small mb-3">Chỉ cần điền mục này nếu bạn đăng ký thi <strong>HSK</strong>. Nếu bạn chỉ đăng ký thi chứng chỉ Tin học, có thể bỏ qua phần này.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Họ và tên (Tiếng Trung)</label>
                        <input type="text" name="chinese_name" class="form-control" value="{{ old('chinese_name', $student->chinese_name) }}" placeholder="Vd: 陈明龙">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nơi sinh</label>
                        <input type="text" name="birthplace" class="form-control" value="{{ old('birthplace', $student->birthplace) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Dân tộc</label>
                        <input type="text" name="ethnic" class="form-control" value="{{ old('ethnic', $student->ethnic) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tôn giáo</label>
                        <input type="text" name="religion" class="form-control" value="{{ old('religion', $student->religion) }}" placeholder="Vd: không...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Quốc tịch</label>
                        <input type="text" name="nationality" class="form-control" value="{{ old('nationality', $student->nationality ?? 'Việt Nam') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Ngôn ngữ mẹ đẻ</label>
                        <input type="text" name="mother_tongue" class="form-control" value="{{ old('mother_tongue', $student->mother_tongue ?? 'Tiếng Việt') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Số năm học tiếng Trung</label>
                        <input type="number" name="chinese_years" class="form-control" min="0" max="99" value="{{ old('chinese_years', $student->chinese_years) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="commit" required>
            <label class="form-check-label small" for="commit">
                Thí sinh cam kết những thông tin kê khai ở trên là đúng, nếu sai thí sinh có thể sẽ bị hủy tư cách dự thi và kết quả thi.
            </label>
        </div>

        <button class="btn btn-primary">Lưu thông tin</button>
    </form>

    <div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cắt ảnh cho đúng tỉ lệ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="crop-container">
                        <img id="cropImage" src="" alt="">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal">Huỷ</button>
                    <button type="button" class="btn btn-primary" id="cropConfirmBtn">
                        <i class="bi bi-crop me-1"></i>Cắt ảnh & dùng ảnh này
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link href="{{ asset('vendor/cropperjs/cropper.min.css') }}" rel="stylesheet">
    <style>
        .image-drop-zone {
            position: relative;
            border: 2px dashed rgba(10,74,117,.35);
            border-radius: .6rem;
            background: #fbfcfe;
            width: 100%;
            /* aspect-ratio set inline per field so the box always matches the crop ratio */
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            overflow: hidden;
            text-align: center;
            color: var(--brand);
            padding: .5rem;
        }
        .image-drop-zone:hover, .image-drop-zone.dragover {
            border-color: var(--accent);
            background: rgba(239,88,35,.05);
        }
        .image-drop-zone .image-drop-preview { width: 100%; height: 100%; }
        .image-drop-zone .image-drop-preview img { width: 100%; height: 100%; object-fit: cover; border-radius: .4rem; display: block; }
        .image-drop-zone .image-drop-placeholder { color: #7a8699; }
        .image-drop-zone .image-drop-placeholder i { font-size: 1.8rem; }
        .image-drop-zone .image-drop-overlay {
            position: absolute; inset: 0;
            background: rgba(10,74,117,.55);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            opacity: 0;
            transition: opacity .15s ease;
            font-size: .85rem;
            font-weight: 600;
            border-radius: .4rem;
        }
        .image-drop-zone:has(.image-drop-preview:not(.d-none)):hover .image-drop-overlay { opacity: 1; }

        .searchable-select { position: relative; }
        .searchable-select-menu {
            position: absolute;
            z-index: 1050;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 2px;
            max-height: 260px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid rgba(16,24,40,.15);
            border-radius: .4rem;
            box-shadow: 0 .5rem 1.25rem rgba(16,24,40,.12);
            display: none;
        }
        .searchable-select-menu.show { display: block; }
        .searchable-select-item { padding: .5rem .8rem; cursor: pointer; font-size: .92rem; }
        .searchable-select-item:hover { background: rgba(10,74,117,.08); }
        .searchable-select-item.empty { color: #9aa5b1; cursor: default; }
        .searchable-select-item.empty:hover { background: transparent; }

        .sample-photo { position: relative; }
        .sample-photo img { width: 100%; aspect-ratio: 3/4; object-fit: cover; }
        .sample-photo.valid img { outline: 3px solid #2fb344; }
        .sample-photo.invalid img { outline: 3px solid #d63939; }
        .sample-tag { font-size: .72rem; font-weight: 600; margin-top: .25rem; }
        .sample-tag.valid { color: #2fb344; }
        .sample-tag.invalid { color: #d63939; }

        .crop-container { max-height: 60vh; }
        .crop-container img { max-width: 100%; display: block; }

    </style>
@endpush

@push('scripts')
    <script src="{{ asset('vendor/cropperjs/cropper.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var cropModalEl = document.getElementById('cropModal');
            var cropModal = new bootstrap.Modal(cropModalEl);
            var cropImage = document.getElementById('cropImage');
            var cropConfirmBtn = document.getElementById('cropConfirmBtn');
            var cropper = null;
            var activeZone = null;

            function openCropper(zone, file) {
                activeZone = zone;
                var reader = new FileReader();
                reader.onload = function (e) {
                    cropImage.src = e.target.result;
                    cropModal.show();
                };
                reader.readAsDataURL(file);
            }

            cropModalEl.addEventListener('shown.bs.modal', function () {
                if (cropper) cropper.destroy();
                cropper = new Cropper(cropImage, {
                    aspectRatio: parseFloat(activeZone.dataset.aspect),
                    viewMode: 1,
                    autoCropArea: 1,
                    background: false,
                });
            });

            cropModalEl.addEventListener('hidden.bs.modal', function () {
                if (cropper) { cropper.destroy(); cropper = null; }
                cropImage.src = '';
            });

            cropConfirmBtn.addEventListener('click', function () {
                if (!cropper || !activeZone) return;

                var canvas = cropper.getCroppedCanvas({ width: 800, imageSmoothingQuality: 'high' });
                canvas.toBlob(function (blob) {
                    var fieldName = activeZone.dataset.name;
                    var file = new File([blob], fieldName + '.jpg', { type: 'image/jpeg' });

                    var input = document.getElementById(activeZone.dataset.target);
                    var dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    input.files = dataTransfer.files;

                    var url = URL.createObjectURL(blob);
                    var preview = activeZone.querySelector('.image-drop-preview');
                    var placeholder = activeZone.querySelector('.image-drop-placeholder');
                    preview.querySelector('img').src = url;
                    preview.classList.remove('d-none');
                    placeholder.classList.add('d-none');

                    cropModal.hide();
                }, 'image/jpeg', 0.9);
            });

            document.querySelectorAll('.image-drop-zone').forEach(function (zone) {
                var input = document.getElementById(zone.dataset.target);

                zone.addEventListener('click', function () {
                    input.click();
                });

                input.addEventListener('change', function () {
                    if (input.files && input.files[0]) {
                        openCropper(zone, input.files[0]);
                    }
                });

                zone.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    zone.classList.add('dragover');
                });
                zone.addEventListener('dragleave', function () {
                    zone.classList.remove('dragover');
                });
                zone.addEventListener('drop', function (e) {
                    e.preventDefault();
                    zone.classList.remove('dragover');
                    var file = e.dataTransfer.files && e.dataTransfer.files[0];
                    if (file && file.type.startsWith('image/')) {
                        openCropper(zone, file);
                    }
                });
            });
        });
    </script>

    <script>
        (function () {
            function stripDiacritics(str) {
                return str.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/gi, 'd').toLowerCase();
            }

            function initSelect(el, getOptions) {
                var input = el.querySelector('.searchable-select-input');
                var hidden = el.querySelector('.searchable-select-value');
                var menu = el.querySelector('.searchable-select-menu');

                function render(filter) {
                    var options = getOptions();
                    var needle = stripDiacritics(filter || '');
                    var filtered = needle
                        ? options.filter(function (o) { return stripDiacritics(o.name).indexOf(needle) !== -1; })
                        : options;

                    menu.innerHTML = '';
                    if (filtered.length === 0) {
                        var empty = document.createElement('div');
                        empty.className = 'searchable-select-item empty';
                        empty.textContent = options.length === 0 ? 'Vui lòng chọn tỉnh/thành phố trước' : 'Không tìm thấy kết quả';
                        menu.appendChild(empty);
                        return;
                    }

                    filtered.slice(0, 200).forEach(function (option) {
                        var item = document.createElement('div');
                        item.className = 'searchable-select-item';
                        item.textContent = option.name;
                        item.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            input.value = option.name;
                            hidden.value = option.name;
                            menu.classList.remove('show');
                            el.dispatchEvent(new CustomEvent('searchable-select:change', { detail: option }));
                        });
                        menu.appendChild(item);
                    });
                }

                input.addEventListener('focus', function () {
                    render('');
                    menu.classList.add('show');
                    input.select();
                });
                input.addEventListener('input', function () {
                    hidden.value = '';
                    render(input.value);
                    menu.classList.add('show');
                });
                document.addEventListener('click', function (e) {
                    if (!el.contains(e.target)) menu.classList.remove('show');
                });

                return { input: input, hidden: hidden, render: render };
            }

            document.addEventListener('DOMContentLoaded', function () {
                var provinceEl = document.querySelector('.searchable-select[data-role="province"]');
                var wardEl = document.querySelector('.searchable-select[data-role="ward"]');
                if (!provinceEl || !wardEl) return;

                Promise.all([
                    fetch('/data/vn-provinces.json').then(function (r) { return r.json(); }),
                    fetch('/data/vn-wards.json').then(function (r) { return r.json(); }),
                ]).then(function (results) {
                    var provinces = results[0];
                    var wards = results[1];
                    var currentProvinceId = null;

                    function provinceIdByName(name) {
                        var match = provinces.find(function (p) { return p.name === name; });
                        return match ? match.id : null;
                    }

                    var provinceCtrl = initSelect(provinceEl, function () { return provinces; });
                    var wardCtrl = initSelect(wardEl, function () {
                        return currentProvinceId ? wards.filter(function (w) { return w.provinceId === currentProvinceId; }) : [];
                    });

                    provinceEl.addEventListener('searchable-select:change', function (e) {
                        currentProvinceId = e.detail.id;
                        wardCtrl.input.value = '';
                        wardCtrl.hidden.value = '';
                        wardCtrl.input.placeholder = 'Gõ để tìm xã/phường...';
                    });

                    if (provinceCtrl.hidden.value) {
                        currentProvinceId = provinceIdByName(provinceCtrl.hidden.value);
                        if (currentProvinceId) {
                            wardCtrl.input.placeholder = 'Gõ để tìm xã/phường...';
                        }
                    }
                }).catch(function () {
                    [provinceEl, wardEl].forEach(function (el) {
                        el.querySelector('.searchable-select-input').placeholder = 'Không tải được dữ liệu, vui lòng tải lại trang';
                    });
                });
            });
        })();
    </script>
@endpush
