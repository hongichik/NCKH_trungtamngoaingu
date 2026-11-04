@extends('layouts.admin')

@section('title', 'Cài đặt website')

@php
    $canEdit = auth()->user()->hasPermission('settings', 'edit');
    $tab = in_array(request('tab'), ['general', 'contact', 'home'], true) ? request('tab') : 'general';
    $staff = old('contact_staff', setting('contact_staff', []));
    $fields = old('home_fields', setting('home_fields', []));
    $reasons = old('home_reasons', setting('home_reasons', []));
    $imageUrl = fn ($key) => setting($key) ? Storage::url(setting($key)) : null;
@endphp

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    .repeat-row { position: relative; padding: .75rem; margin-bottom: .5rem; border: 1px solid var(--tblr-border-color); border-radius: .5rem; background: var(--tblr-bg-surface-secondary, #f8fafc); }
    .icon-preview { width: 2.4rem; height: 2.4rem; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; border-radius: .4rem; background: #fff; border: 1px solid var(--tblr-border-color); color: var(--tblr-primary); }
</style>
@endpush

@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" id="settings-form">
        @csrf
        <input type="hidden" name="_tab" id="current-tab" value="{{ $tab }}">

        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item"><a href="#tab-general" class="nav-link {{ $tab === 'general' ? 'active' : '' }}" data-bs-toggle="tab" data-tab="general" role="tab"><i class="ti ti-world me-1"></i>Thông tin chung</a></li>
                    <li class="nav-item"><a href="#tab-contact" class="nav-link {{ $tab === 'contact' ? 'active' : '' }}" data-bs-toggle="tab" data-tab="contact" role="tab"><i class="ti ti-address-book me-1"></i>Liên hệ &amp; mạng xã hội</a></li>
                    <li class="nav-item"><a href="#tab-home" class="nav-link {{ $tab === 'home' ? 'active' : '' }}" data-bs-toggle="tab" data-tab="home" role="tab"><i class="ti ti-home me-1"></i>Nội dung trang chủ</a></li>
                </ul>
            </div>

            <div class="card-body">
                <fieldset @disabled(! $canEdit)>
                <div class="tab-content">
                    {{-- ===== General ===== --}}
                    <div class="tab-pane {{ $tab === 'general' ? 'active show' : '' }}" id="tab-general" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-lg-7">
                                <div class="mb-3">
                                    <label class="form-label required">Tên website / trung tâm</label>
                                    <input type="text" name="site_name" class="form-control" value="{{ $s('site_name', config('app.name')) }}" maxlength="150" required>
                                    <div class="form-hint">Hiển thị trên tab trình duyệt, chân trang, kết quả tìm kiếm Google.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Giới thiệu ngắn (chân trang)</label>
                                    <textarea name="site_intro" class="form-control" rows="3" maxlength="500">{{ $s('site_intro') }}</textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Đơn vị chủ quản</label>
                                        <input type="text" name="parent_org_name" class="form-control" value="{{ $s('parent_org_name') }}" maxlength="150">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Website đơn vị chủ quản</label>
                                        <input type="url" name="parent_org_url" class="form-control" value="{{ $s('parent_org_url') }}" placeholder="https://">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mô tả mặc định cho Google</label>
                                    <textarea name="meta_description" class="form-control" rows="3" maxlength="300">{{ $s('meta_description') }}</textarea>
                                    <div class="form-hint">Dùng cho các trang không có mô tả riêng. Nên dài 120–160 ký tự.</div>
                                </div>
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Màu chính</label>
                                        <input type="color" name="color_brand" class="form-control form-control-color w-100" value="{{ $s('color_brand', '#0a4a75') }}">
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Màu nhấn (nút bấm)</label>
                                        <input type="color" name="color_accent" class="form-control form-control-color w-100" value="{{ $s('color_accent', '#ef5823') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                @include('admin.partials.image-field', ['name' => 'logo', 'label' => 'Logo đầu trang', 'currentUrl' => $imageUrl('logo') ?? asset('images/logo.jpg'), 'aspect' => null, 'removeName' => setting('logo') ? 'remove_logo' : null, 'hint' => 'Ảnh ngang, nền trắng hoặc trong suốt.'])
                                @include('admin.partials.image-field', ['name' => 'logo_footer', 'label' => 'Logo chân trang', 'currentUrl' => $imageUrl('logo_footer') ?? asset('images/logo-mark.png'), 'aspect' => null, 'removeName' => setting('logo_footer') ? 'remove_logo_footer' : null])
                                @include('admin.partials.image-field', ['name' => 'favicon', 'label' => 'Biểu tượng tab trình duyệt (favicon)', 'currentUrl' => $imageUrl('favicon') ?? asset('images/favicon.png'), 'aspect' => 1, 'removeName' => setting('favicon') ? 'remove_favicon' : null, 'hint' => 'Ảnh vuông, PNG.'])
                            </div>
                        </div>
                    </div>

                    {{-- ===== Contact ===== --}}
                    <div class="tab-pane {{ $tab === 'contact' ? 'active show' : '' }}" id="tab-contact" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label class="form-label">Địa chỉ</label>
                                    <textarea name="contact_address" class="form-control" rows="2" maxlength="500">{{ $s('contact_address', config('site.contact.address')) }}</textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email chung</label>
                                    <input type="email" name="contact_email" class="form-control" value="{{ $s('contact_email') }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Vị trí trên bản đồ</label>
                                    <input type="text" name="map_query" class="form-control" value="{{ $s('map_query') }}" placeholder="Để trống để dùng địa chỉ ở trên">
                                    <div class="form-hint">Nhập tên địa điểm hoặc toạ độ (VD: 21.0367,106.7795) nếu bản đồ chỉ sai vị trí.</div>
                                </div>
                                <h4 class="mt-4">Mạng xã hội</h4>
                                <div class="mb-2 input-group"><span class="input-group-text"><i class="ti ti-brand-facebook"></i></span><input type="url" name="social_facebook" class="form-control" value="{{ $s('social_facebook') }}" placeholder="Link fanpage Facebook"></div>
                                <div class="mb-2 input-group"><span class="input-group-text">Zalo</span><input type="text" name="social_zalo" class="form-control" value="{{ $s('social_zalo') }}" placeholder="Số Zalo hoặc link zalo.me/..."></div>
                                <div class="mb-2 input-group"><span class="input-group-text"><i class="ti ti-brand-youtube"></i></span><input type="url" name="social_youtube" class="form-control" value="{{ $s('social_youtube') }}" placeholder="Link kênh YouTube"></div>
                                <div class="form-hint">Để trống mục nào thì biểu tượng đó sẽ không hiện ở chân trang.</div>
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label">Cán bộ tư vấn / số điện thoại liên hệ</label>
                                <div class="repeat-list" data-name="contact_staff" data-template="tpl-staff">
                                    @foreach ($staff as $i => $row)
                                        @include('admin.settings.partials.staff-row', ['i' => $i, 'row' => $row])
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary repeat-add" data-list="contact_staff"><i class="ti ti-plus me-1"></i>Thêm người liên hệ</button>
                                <div class="form-hint">Hiển thị ở chân trang và mục "Địa chỉ &amp; Liên hệ" trên trang chủ.</div>
                            </div>
                        </div>
                    </div>

                    {{-- ===== Homepage ===== --}}
                    <div class="tab-pane {{ $tab === 'home' ? 'active show' : '' }}" id="tab-home" role="tabpanel">
                        <p class="text-muted">Banner trang chủ được quản lý riêng tại <a href="{{ route('admin.banners.index') }}">Banner trang chủ</a>.</p>
                        @foreach (['home_fields' => ['Khu vực "Lĩnh vực đào tạo"', $fields], 'home_reasons' => ['Khu vực "Vì sao chọn chúng tôi"', $reasons]] as $key => [$heading, $rows])
                            <h3 class="mt-2">{{ $heading }}</h3>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tiêu đề</label>
                                    <input type="text" name="{{ $key }}_title" class="form-control" value="{{ $s($key.'_title') }}" maxlength="150">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Dòng mô tả</label>
                                    <input type="text" name="{{ $key }}_subtitle" class="form-control" value="{{ $s($key.'_subtitle') }}" maxlength="255">
                                </div>
                            </div>
                            <div class="repeat-list" data-name="{{ $key }}" data-template="tpl-card">
                                @foreach ($rows as $i => $row)
                                    @include('admin.settings.partials.card-row', ['name' => $key, 'i' => $i, 'row' => $row])
                                @endforeach
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary repeat-add mb-4" data-list="{{ $key }}"><i class="ti ti-plus me-1"></i>Thêm mục</button>
                            <div class="form-hint mb-4">Để trống tất cả các mục thì khu vực này sẽ được ẩn khỏi trang chủ.</div>
                        @endforeach
                    </div>
                </div>
                </fieldset>
            </div>

            @if ($canEdit)
                <div class="card-footer d-flex gap-2">
                    <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Lưu cài đặt</button>
                    <a href="{{ route('home') }}" target="_blank" class="btn btn-outline-secondary"><i class="ti ti-external-link me-1"></i>Xem website</a>
                </div>
            @endif
        </div>
    </form>

    {{-- Row templates for the "add" buttons; __i__ is replaced with a fresh index. --}}
    <template id="tpl-staff">@include('admin.settings.partials.staff-row', ['i' => '__i__', 'row' => []])</template>
    <template id="tpl-card">@include('admin.settings.partials.card-row', ['name' => '__name__', 'i' => '__i__', 'row' => []])</template>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('settings-form');

    // Remember the open tab so saving returns to it.
    form.querySelectorAll('[data-tab]').forEach((a) => a.addEventListener('shown.bs.tab', () => {
        document.getElementById('current-tab').value = a.dataset.tab;
    }));

    let counter = Date.now();
    form.querySelectorAll('.repeat-add').forEach((btn) => btn.addEventListener('click', () => {
        const list = form.querySelector(`.repeat-list[data-name="${btn.dataset.list}"]`);
        const html = document.getElementById(list.dataset.template).innerHTML
            .replaceAll('__name__', list.dataset.name)
            .replaceAll('__i__', counter++);
        list.insertAdjacentHTML('beforeend', html);
        list.lastElementChild.querySelector('input, textarea')?.focus();
    }));

    form.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-repeat]');
        if (!btn) return;
        const row = btn.closest('.repeat-row');
        if (btn.dataset.repeat === 'remove') row.remove();
        if (btn.dataset.repeat === 'up' && row.previousElementSibling) row.after(row.previousElementSibling);
        if (btn.dataset.repeat === 'down' && row.nextElementSibling) row.before(row.nextElementSibling);
    });

    form.addEventListener('change', (e) => {
        if (!e.target.matches('.icon-select')) return;
        e.target.closest('.repeat-row').querySelector('.icon-preview i').className = 'bi ' + e.target.value;
    });
})();
</script>
@endpush
