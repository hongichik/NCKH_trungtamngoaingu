@extends('layouts.admin')

@section('title', 'Menu website')

@php
    $canCreate = auth()->user()->hasPermission('menus', 'create');
    $canEdit = auth()->user()->hasPermission('menus', 'edit');
    $canDelete = auth()->user()->hasPermission('menus', 'delete');
    $nested = $locations[$location]['nested'];
@endphp

@push('styles')
<style>
    .menu-tree, .menu-children { list-style: none; margin: 0; padding: 0; }
    .menu-children { margin-left: 2.25rem; }
    .menu-row {
        display: flex; align-items: center; gap: .75rem;
        padding: .6rem .75rem; margin-bottom: .4rem;
        background: var(--tblr-bg-surface, #fff);
        border: 1px solid var(--tblr-border-color, #e6e7e9); border-radius: .5rem;
    }
    .menu-row.is-hidden { opacity: .55; }
    .menu-handle { cursor: grab; color: var(--tblr-secondary, #667382); font-size: 1.25rem; }
    .menu-item.dragging > .menu-row { opacity: .4; border-style: dashed; }
    .menu-drop-zone {
        display: none; margin: 0 0 .4rem 2.25rem; padding: .35rem .75rem;
        border: 1px dashed var(--tblr-primary); border-radius: .5rem;
        color: var(--tblr-primary); font-size: .8rem;
    }
    .is-dragging-root .menu-drop-zone { display: block; }
    .menu-target { color: var(--tblr-secondary, #667382); font-size: .85rem; }
</style>
@endpush

@section('content')
    <ul class="nav nav-tabs mb-3">
        @foreach ($locations as $key => $loc)
            <li class="nav-item">
                <a class="nav-link {{ $key === $location ? 'active' : '' }}" href="{{ route('admin.menus.index', ['location' => $key]) }}">{{ $loc['label'] }}</a>
            </li>
        @endforeach
    </ul>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        @if ($canCreate)
            <a href="{{ route('admin.menus.create', ['location' => $location]) }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Thêm mục menu</a>
        @endif
        <a href="{{ route('home') }}" target="_blank" class="btn btn-outline-secondary"><i class="ti ti-external-link me-1"></i>Xem trên website</a>
        <span id="reorder-status" class="small ms-auto"></span>
    </div>

    @if ($canEdit && $items->isNotEmpty())
        <p class="text-muted small mb-3">
            <i class="ti ti-hand-move me-1"></i>Kéo biểu tượng <i class="ti ti-grip-vertical"></i> để sắp xếp. Thứ tự được lưu tự động.
            @if ($nested)
                Thả một mục vào khung "Thả vào đây" bên dưới mục khác để biến nó thành menu con (menu thả xuống).
            @endif
        </p>
    @endif

    @if ($items->isEmpty())
        <div class="card"><div class="card-body text-center text-muted py-5">
            Chưa có mục nào ở vị trí này.
            @if ($canCreate)
                <a href="{{ route('admin.menus.create', ['location' => $location]) }}">Thêm mục đầu tiên</a>.
            @endif
        </div></div>
    @else
        <ul class="menu-tree" id="menu-tree" data-nested="{{ $nested ? 1 : 0 }}">
            @foreach ($items as $item)
                <li class="menu-item" data-id="{{ $item->id }}" draggable="{{ $canEdit ? 'true' : 'false' }}">
                    @include('admin.menus.partials.row', ['item' => $item])
                    @if ($nested)
                        <ul class="menu-children">
                            @foreach ($item->children as $child)
                                <li class="menu-item" data-id="{{ $child->id }}" draggable="{{ $canEdit ? 'true' : 'false' }}">
                                    @include('admin.menus.partials.row', ['item' => $child, 'isChild' => true])
                                </li>
                            @endforeach
                        </ul>
                        <div class="menu-drop-zone">Thả vào đây để làm menu con của "{{ $item->title }}"</div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection

@if ($canEdit)
@push('scripts')
<script>
(function () {
    const tree = document.getElementById('menu-tree');
    if (!tree) return;

    const nested = tree.dataset.nested === '1';
    const status = document.getElementById('reorder-status');
    let dragged = null;

    const hasChildren = (li) => !!li.querySelector(':scope > .menu-children > .menu-item');
    const containerOf = (li) => li.parentElement;

    tree.addEventListener('dragstart', (e) => {
        const li = e.target.closest('.menu-item');
        if (!li) return;
        dragged = li;
        li.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', li.dataset.id);
        // Only items without children may become children (max 2 levels).
        if (nested && !hasChildren(li)) tree.classList.add('is-dragging-root');
    });

    tree.addEventListener('dragend', () => {
        if (dragged) dragged.classList.remove('dragging');
        tree.classList.remove('is-dragging-root');
        dragged = null;
    });

    tree.addEventListener('dragover', (e) => {
        if (!dragged) return;

        // Dropping onto a "make child of" zone.
        const zone = e.target.closest('.menu-drop-zone');
        if (zone) {
            e.preventDefault();
            const list = zone.parentElement.querySelector(':scope > .menu-children');
            if (list && zone.parentElement !== dragged && dragged.parentElement !== list) list.appendChild(dragged);
            return;
        }

        const target = e.target.closest('.menu-item');
        if (!target || target === dragged || dragged.contains(target)) return;

        const targetList = containerOf(target);
        // An item with children can't be moved into a submenu.
        if (targetList.classList.contains('menu-children') && hasChildren(dragged)) return;

        e.preventDefault();
        const row = target.querySelector(':scope > .menu-row');
        const rect = row.getBoundingClientRect();
        const after = e.clientY > rect.top + rect.height / 2;
        targetList.insertBefore(dragged, after ? target.nextSibling : target);
    });

    tree.addEventListener('drop', (e) => {
        if (!dragged) return;
        e.preventDefault();
        save();
    });

    function save() {
        const items = [];
        tree.querySelectorAll(':scope > .menu-item').forEach((root) => {
            items.push({ id: +root.dataset.id, parent_id: null });
            root.querySelectorAll(':scope > .menu-children > .menu-item').forEach((child) => {
                items.push({ id: +child.dataset.id, parent_id: +root.dataset.id });
            });
        });

        // Keep child/parent badges in sync without reloading.
        tree.querySelectorAll('.menu-item').forEach((li) => {
            const badge = li.querySelector(':scope > .menu-row .js-child-badge');
            if (badge) badge.classList.toggle('d-none', !li.parentElement.classList.contains('menu-children'));
        });

        status.className = 'small ms-auto text-muted';
        status.textContent = 'Đang lưu...';

        fetch(@json(route('admin.menus.reorder')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token()),
            },
            body: JSON.stringify({ location: @json($location), items }),
        })
            .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                status.className = 'small ms-auto ' + (ok ? 'text-success' : 'text-danger');
                status.innerHTML = (ok ? '<i class="ti ti-check me-1"></i>' : '<i class="ti ti-alert-triangle me-1"></i>') + (body.message || (ok ? 'Đã lưu.' : 'Lưu thất bại.'));
            })
            .catch(() => {
                status.className = 'small ms-auto text-danger';
                status.textContent = 'Không lưu được thứ tự, vui lòng thử lại.';
            });
    }
})();
</script>
@endpush
@endif
