@extends('layouts.admin')

@section('title', $item->exists ? 'Sửa mục menu' : 'Thêm mục menu')

@php
    $type = old('link_type', $item->link_type ?? 'route');
    $value = fn (string $t) => old($t.'_value', $item->link_type === $t ? $item->link_value : null);
@endphp

@section('content')
    <form method="POST" action="{{ $item->exists ? route('admin.menus.update', $item) : route('admin.menus.store') }}" style="max-width: 720px;" id="menu-form">
        @csrf
        @if ($item->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required">Tên hiển thị</label>
                    <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $item->title) }}" maxlength="120" required autofocus>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Vị trí</label>
                        <select name="location" id="location" class="form-select">
                            @foreach ($locations as $key => $loc)
                                <option value="{{ $key }}" data-nested="{{ $loc['nested'] ? 1 : 0 }}" @selected(old('location', $item->location) === $key)>{{ $loc['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3" id="parent-wrap">
                        <label class="form-label">Thuộc menu cha</label>
                        <select name="parent_id" id="parent_id" class="form-select">
                            <option value="">— Không (mục cấp 1) —</option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}" data-location="{{ $parent->location }}" @selected((string) old('parent_id', $item->parent_id) === (string) $parent->id)>{{ $parent->title }}</option>
                            @endforeach
                        </select>
                        <div class="form-hint">Chọn menu cha để mục này hiện trong menu thả xuống.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required">Liên kết tới</label>
                    <div class="form-selectgroup">
                        @foreach (\App\Models\MenuItem::LINK_TYPES as $key => $label)
                            <label class="form-selectgroup-item">
                                <input type="radio" name="link_type" value="{{ $key }}" class="form-selectgroup-input" @checked($type === $key)>
                                <span class="form-selectgroup-label">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mb-3 link-field" data-type="route">
                    <label class="form-label">Trang hệ thống</label>
                    <select name="route_value" class="form-select js-fill-title">
                        <option value="">— Chọn trang —</option>
                        @foreach ($routes as $key => $route)
                            <option value="{{ $key }}" @selected($value('route') === $key)>{{ $route['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3 link-field" data-type="page">
                    <label class="form-label">Trang tĩnh</label>
                    <select name="page_value" class="form-select js-fill-title">
                        <option value="">— Chọn trang tĩnh —</option>
                        @foreach ($pages as $page)
                            <option value="{{ $page->id }}" @selected((string) $value('page') === (string) $page->id)>{{ $page->title }}{{ $page->status !== 'published' ? ' (chưa xuất bản)' : '' }}</option>
                        @endforeach
                    </select>
                    @if ($pages->isEmpty())
                        <div class="form-hint">Chưa có trang tĩnh nào.</div>
                    @endif
                </div>

                <div class="mb-3 link-field" data-type="category">
                    <label class="form-label">Danh mục tin</label>
                    <select name="category_value" class="form-select js-fill-title">
                        <option value="">— Chọn danh mục —</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) $value('category') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3 link-field" data-type="url">
                    <label class="form-label">Đường dẫn</label>
                    <input type="text" name="url_value" class="form-control" value="{{ $value('url') }}" placeholder="https://... hoặc /duong-dan-trong-web" maxlength="500">
                    <div class="form-hint">Có thể nhập liên kết ngoài (https://...), đường dẫn trong website (/lien-he), hoặc tel:/mailto:. Để "#" nếu chỉ dùng làm tiêu đề cho menu thả xuống.</div>
                </div>

                <div class="mb-2">
                    <label class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true))>
                        <span class="form-check-label">Hiển thị trên website</span>
                    </label>
                    <label class="form-check form-switch">
                        <input type="hidden" name="open_new_tab" value="0">
                        <input class="form-check-input" type="checkbox" name="open_new_tab" value="1" @checked(old('open_new_tab', $item->open_new_tab))>
                        <span class="form-check-label">Mở trong tab mới</span>
                    </label>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary">Lưu</button>
                <a href="{{ route('admin.menus.index', ['location' => $item->location]) }}" class="btn btn-link">Huỷ</a>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('menu-form');
    const location = document.getElementById('location');
    const parentWrap = document.getElementById('parent-wrap');
    const parent = document.getElementById('parent_id');
    const title = document.getElementById('title');

    function syncLinkType() {
        const type = form.querySelector('input[name="link_type"]:checked').value;
        form.querySelectorAll('.link-field').forEach((el) => {
            el.hidden = el.dataset.type !== type;
        });
    }

    function syncParent() {
        const nested = location.selectedOptions[0].dataset.nested === '1';
        parentWrap.hidden = !nested;
        [...parent.options].forEach((opt) => {
            if (!opt.value) return;
            opt.hidden = opt.dataset.location !== location.value;
            if (opt.hidden && opt.selected) parent.value = '';
        });
        if (!nested) parent.value = '';
    }

    // Fill the title from the chosen page/category if the admin hasn't typed one yet.
    let titleTouched = title.value.trim() !== '';
    title.addEventListener('input', () => { titleTouched = title.value.trim() !== ''; });
    form.querySelectorAll('.js-fill-title').forEach((select) => {
        select.addEventListener('change', () => {
            if (!titleTouched && select.value) {
                title.value = select.selectedOptions[0].text.replace(/ \(chưa xuất bản\)$/, '');
            }
        });
    });

    form.querySelectorAll('input[name="link_type"]').forEach((r) => r.addEventListener('change', syncLinkType));
    location.addEventListener('change', syncParent);
    syncLinkType();
    syncParent();
})();
</script>
@endpush
