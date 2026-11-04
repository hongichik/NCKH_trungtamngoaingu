@extends('layouts.admin')

@section('title', 'Banner trang chủ')

@php
    $canCreate = auth()->user()->hasPermission('banners', 'create');
    $canEdit = auth()->user()->hasPermission('banners', 'edit');
    $canDelete = auth()->user()->hasPermission('banners', 'delete');
    $gradients = [
        'brand' => 'linear-gradient(135deg, var(--site-brand), #073657)',
        'accent' => 'linear-gradient(135deg, var(--site-accent), var(--site-brand))',
        'dark' => 'linear-gradient(135deg, #1f2937, #0b1220)',
    ];
@endphp

@push('styles')
<style>
    :root { --site-brand: {{ setting('color_brand', '#0a4a75') }}; --site-accent: {{ setting('color_accent', '#ef5823') }}; }
    .banner-row { display: flex; align-items: center; gap: 1rem; padding: .75rem; margin-bottom: .5rem; background: var(--tblr-bg-surface, #fff); border: 1px solid var(--tblr-border-color); border-radius: .5rem; }
    .banner-row.is-hidden { opacity: .55; }
    .banner-row.dragging { opacity: .4; border-style: dashed; }
    .banner-thumb { width: 200px; aspect-ratio: 16 / 6; flex-shrink: 0; border-radius: .4rem; overflow: hidden; background-size: cover; background-position: center; color: #fff; display: flex; align-items: flex-end; padding: .4rem .5rem; font-size: .7rem; font-weight: 600; line-height: 1.2; }
    .banner-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .banner-handle { cursor: grab; color: var(--tblr-secondary); font-size: 1.25rem; }
    @media (max-width: 575.98px) { .banner-thumb { width: 110px; } }
</style>
@endpush

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        @if ($canCreate)
            <a href="{{ route('admin.banners.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Thêm banner</a>
        @endif
        <a href="{{ route('home') }}" target="_blank" class="btn btn-outline-secondary"><i class="ti ti-external-link me-1"></i>Xem trang chủ</a>
        <span id="reorder-status" class="small ms-auto"></span>
    </div>

    @if ($banners->isEmpty())
        <div class="card"><div class="card-body text-center text-muted py-5">
            Chưa có banner nào. Trang chủ sẽ không hiển thị khu vực banner.
        </div></div>
    @else
        @if ($canEdit && $banners->count() > 1)
            <p class="text-muted small"><i class="ti ti-hand-move me-1"></i>Kéo <i class="ti ti-grip-vertical"></i> để đổi thứ tự hiển thị. Thứ tự được lưu tự động.</p>
        @endif

        <div id="banner-list">
            @foreach ($banners as $banner)
                <div class="banner-row {{ $banner->is_active ? '' : 'is-hidden' }}" data-id="{{ $banner->id }}" draggable="{{ $canEdit ? 'true' : 'false' }}">
                    @if ($canEdit)
                        <span class="banner-handle"><i class="ti ti-grip-vertical"></i></span>
                    @endif

                    @if ($banner->type === 'image')
                        <div class="banner-thumb p-0"><img src="{{ $banner->image_url }}" alt=""></div>
                    @else
                        <div class="banner-thumb" style="background: {{ $banner->image_url ? 'linear-gradient(rgba(0,0,0,.35), rgba(0,0,0,.55)), url('.$banner->image_url.') center/cover' : $gradients[$banner->background] ?? $gradients['brand'] }};">
                            {{ Str::limit($banner->title, 60) }}
                        </div>
                    @endif

                    <div class="flex-fill min-w-0">
                        <div class="fw-medium text-truncate">{{ $banner->title }}</div>
                        <div class="small text-muted">
                            {{ $banner->type === 'image' ? 'Ảnh poster' : 'Chữ trên nền' }}
                            @if ($banner->button_href) · <i class="ti ti-link"></i> {{ Str::limit($banner->button_url, 40) }} @endif
                        </div>
                        <div class="mt-1">
                            @unless ($banner->is_active)<span class="badge bg-secondary-lt">Đang ẩn</span>@endunless
                            @if ($banner->schedule_status)<span class="badge bg-warning-lt">{{ $banner->schedule_status }}</span>@endif
                            @if ($banner->image_mobile)<span class="badge bg-azure-lt"><i class="ti ti-device-mobile"></i> Có ảnh điện thoại</span>@endif
                        </div>
                    </div>

                    <div class="d-flex gap-1 flex-shrink-0">
                        @if ($canEdit)
                            <form method="POST" action="{{ route('admin.banners.toggle', $banner) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary" title="{{ $banner->is_active ? 'Ẩn' : 'Hiện' }}"><i class="ti {{ $banner->is_active ? 'ti-eye' : 'ti-eye-off' }}"></i></button>
                            </form>
                            <a href="{{ route('admin.banners.edit', $banner) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                        @endif
                        @if ($canDelete)
                            <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" data-confirm="Xoá banner &quot;{{ $banner->title }}&quot;?" onsubmit="return confirm(this.dataset.confirm)">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xoá</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection

@if ($canEdit)
@push('scripts')
<script>
(function () {
    const list = document.getElementById('banner-list');
    if (!list) return;
    const status = document.getElementById('reorder-status');
    let dragged = null;

    list.addEventListener('dragstart', (e) => {
        dragged = e.target.closest('.banner-row');
        if (!dragged) return;
        dragged.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', dragged.dataset.id);
    });
    list.addEventListener('dragend', () => { dragged?.classList.remove('dragging'); dragged = null; });
    list.addEventListener('dragover', (e) => {
        const target = e.target.closest('.banner-row');
        if (!dragged || !target || target === dragged) return;
        e.preventDefault();
        const rect = target.getBoundingClientRect();
        list.insertBefore(dragged, e.clientY > rect.top + rect.height / 2 ? target.nextSibling : target);
    });
    list.addEventListener('drop', (e) => {
        if (!dragged) return;
        e.preventDefault();
        const ids = [...list.querySelectorAll('.banner-row')].map((row) => +row.dataset.id);
        status.className = 'small ms-auto text-muted';
        status.textContent = 'Đang lưu...';
        fetch(@json(route('admin.banners.reorder')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
            body: JSON.stringify({ ids }),
        })
            .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                status.className = 'small ms-auto ' + (ok ? 'text-success' : 'text-danger');
                status.textContent = body.message || (ok ? 'Đã lưu.' : 'Lưu thất bại.');
            })
            .catch(() => { status.className = 'small ms-auto text-danger'; status.textContent = 'Không lưu được thứ tự.'; });
    });
})();
</script>
@endpush
@endif
