@extends('layouts.admin')

@section('title', $banner->exists ? 'Sửa banner' : 'Thêm banner')

@php
    $type = old('type', $banner->type);
    $dt = fn ($value) => $value ? $value->format('Y-m-d\TH:i') : '';
@endphp

@section('content')
    {{-- POST (no spoofed PUT): this form has file inputs. --}}
    <form method="POST" action="{{ $banner->exists ? route('admin.banners.update', $banner) : route('admin.banners.store') }}" enctype="multipart/form-data" id="banner-form">
        @csrf

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <label class="form-label required">Loại banner</label>
                        <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column flex-md-row mb-3">
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="type" value="image" class="form-selectgroup-input" @checked($type === 'image')>
                                <span class="form-selectgroup-label d-flex align-items-center p-3">
                                    <i class="ti ti-photo fs-2 me-3"></i>
                                    <span><strong>Ảnh poster</strong><br><span class="text-muted small">Ảnh thiết kế sẵn, đã có chữ. Hiển thị nguyên ảnh.</span></span>
                                </span>
                            </label>
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="type" value="text" class="form-selectgroup-input" @checked($type === 'text')>
                                <span class="form-selectgroup-label d-flex align-items-center p-3">
                                    <i class="ti ti-typography fs-2 me-3"></i>
                                    <span><strong>Chữ trên nền</strong><br><span class="text-muted small">Nhập tiêu đề, nội dung, nút bấm. Nền màu hoặc ảnh.</span></span>
                                </span>
                            </label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">
                                <span class="type-text">Tiêu đề lớn</span>
                                <span class="type-image">Tên banner <span class="text-muted fw-normal">(dùng làm mô tả ảnh cho Google &amp; người khiếm thị)</span></span>
                            </label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $banner->title) }}" maxlength="255" required>
                        </div>

                        <div class="type-text">
                            <div class="mb-3">
                                <label class="form-label">Nhãn nhỏ phía trên tiêu đề</label>
                                <input type="text" name="badge" class="form-control" value="{{ old('badge', $banner->badge) }}" maxlength="100" placeholder="VD: Tuyển sinh">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Đoạn mô tả</label>
                                <textarea name="description" class="form-control" rows="3" maxlength="1000">{{ old('description', $banner->description) }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Danh sách gạch đầu dòng</label>
                                <textarea name="bullet_points" class="form-control" rows="3" maxlength="1000" placeholder="Mỗi dòng một ý">{{ old('bullet_points', $banner->bullet_points) }}</textarea>
                                <div class="form-hint">Mỗi dòng là một ý, hiển thị kèm dấu ✓.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Màu nền</label>
                                <select name="background" class="form-select">
                                    @foreach (\App\Models\Banner::BACKGROUNDS as $key => $label)
                                        <option value="{{ $key }}" @selected(old('background', $banner->background) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="form-hint">Nếu có ảnh nền bên phải, ảnh sẽ được dùng thay cho màu (phủ lớp tối để chữ dễ đọc).</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3 type-text">
                                <label class="form-label">Chữ trên nút bấm</label>
                                <input type="text" name="button_text" class="form-control" value="{{ old('button_text', $banner->button_text) }}" maxlength="100" placeholder="VD: Đăng ký ngay">
                            </div>
                            <div class="col-md-6 mb-3 type-link-col">
                                <label class="form-label">
                                    <span class="type-text">Liên kết của nút</span>
                                    <span class="type-image">Liên kết khi bấm vào banner</span>
                                </label>
                                <input type="text" name="button_url" class="form-control" value="{{ old('button_url', $banner->button_url) }}" maxlength="500" placeholder="https://... , /lien-he hoặc tel:0974024655">
                            </div>
                        </div>
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="open_new_tab" value="1" @checked(old('open_new_tab', $banner->open_new_tab))>
                            <span class="form-check-label">Mở liên kết trong tab mới</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title">Hiển thị</h3></div>
                    <div class="card-body">
                        <label class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $banner->is_active ?? true))>
                            <span class="form-check-label">Hiển thị trên trang chủ</span>
                        </label>
                        <div class="mb-3">
                            <label class="form-label">Bắt đầu hiển thị từ</label>
                            <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', $dt($banner->starts_at)) }}">
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Tự ẩn sau</label>
                            <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', $dt($banner->ends_at)) }}">
                        </div>
                        <div class="form-hint">Để trống nếu muốn hiển thị liên tục. Hữu ích cho banner tuyển sinh có thời hạn.</div>

                        <div class="mt-3 d-grid gap-2">
                            <button class="btn btn-primary">Lưu</button>
                            <a href="{{ route('admin.banners.index') }}" class="btn btn-link">Huỷ</a>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title"><span class="type-image">Ảnh banner</span><span class="type-text">Ảnh nền (không bắt buộc)</span></h3></div>
                    <div class="card-body">
                        @include('admin.partials.image-field', [
                            'name' => 'image',
                            'label' => 'Ảnh cho máy tính',
                            'currentUrl' => $banner->image_url,
                            'aspect' => null,
                            'removeName' => 'remove_image',
                            'hint' => 'Khuyên dùng ảnh ngang khoảng 1920 × 600px. Ảnh lớn sẽ tự được thu nhỏ.',
                        ])
                        @include('admin.partials.image-field', [
                            'name' => 'image_mobile',
                            'label' => 'Ảnh cho điện thoại (không bắt buộc)',
                            'currentUrl' => $banner->image_mobile_url,
                            'aspect' => null,
                            'removeName' => 'remove_image_mobile',
                            'hint' => 'Ảnh dọc hoặc vuông cho màn hình nhỏ. Để trống sẽ dùng ảnh máy tính.',
                        ])
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('banner-form');
    function sync() {
        const type = form.querySelector('input[name="type"]:checked').value;
        form.querySelectorAll('.type-text').forEach((el) => { el.hidden = type !== 'text'; });
        form.querySelectorAll('.type-image').forEach((el) => { el.hidden = type !== 'image'; });
    }
    form.querySelectorAll('input[name="type"]').forEach((r) => r.addEventListener('change', sync));
    sync();
})();
</script>
@endpush
