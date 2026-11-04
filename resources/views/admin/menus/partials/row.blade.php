<div class="menu-row {{ $item->is_active ? '' : 'is-hidden' }}">
    @if ($canEdit)
        <span class="menu-handle" title="Kéo để sắp xếp"><i class="ti ti-grip-vertical"></i></span>
    @endif

    <div class="flex-fill min-w-0">
        <div class="fw-medium">
            {{ $item->title }}
            <span class="badge bg-azure-lt ms-1 js-child-badge {{ ($isChild ?? false) ? '' : 'd-none' }}">Menu con</span>
            @unless ($item->is_active)
                <span class="badge bg-secondary-lt ms-1">Đang ẩn</span>
            @endunless
            @if ($item->open_new_tab)
                <i class="ti ti-external-link text-muted ms-1" title="Mở tab mới"></i>
            @endif
        </div>
        <div class="menu-target text-truncate">
            <span class="badge bg-light text-dark me-1">{{ \App\Models\MenuItem::LINK_TYPES[$item->link_type] ?? $item->link_type }}</span>
            {{ $item->target_label }}
        </div>
    </div>

    <div class="d-flex gap-1 flex-shrink-0">
        @if ($canCreate && $nested && ! ($isChild ?? false))
            <a href="{{ route('admin.menus.create', ['location' => $item->location, 'parent_id' => $item->id]) }}" class="btn btn-sm btn-outline-primary" title="Thêm menu con">
                <i class="ti ti-subtask"></i>
            </a>
        @endif
        @if ($canEdit)
            <form method="POST" action="{{ route('admin.menus.toggle', $item) }}" class="d-inline">
                @csrf
                <button class="btn btn-sm btn-outline-secondary" title="{{ $item->is_active ? 'Ẩn khỏi website' : 'Hiện trên website' }}">
                    <i class="ti {{ $item->is_active ? 'ti-eye' : 'ti-eye-off' }}"></i>
                </button>
            </form>
            <a href="{{ route('admin.menus.edit', $item) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
        @endif
        @if ($canDelete)
            <form method="POST" action="{{ route('admin.menus.destroy', $item) }}" class="d-inline" data-confirm="Xoá mục &quot;{{ $item->title }}&quot;{{ ($isChild ?? false) ? '' : ' và toàn bộ menu con của nó' }}?" onsubmit="return confirm(this.dataset.confirm)">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Xoá</button>
            </form>
        @endif
    </div>
</div>
