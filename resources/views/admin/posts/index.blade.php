@extends('layouts.admin')

@section('title', 'Bài viết')

@php
    $canCreate = auth()->user()->hasPermission('posts', 'create');
    $canEdit = auth()->user()->hasPermission('posts', 'edit');
    $canDelete = auth()->user()->hasPermission('posts', 'delete');
    $status = $filters['status'] ?? null;
    $allCount = $statusCounts->sum();
    // Same filters, different status tab.
    $tabUrl = fn ($value) => route('admin.posts.index', array_filter(array_merge(request()->except(['status', 'page']), ['status' => $value])));
    // Same filters plus one change (used by category / author links in the table).
    $filterUrl = fn (array $change) => route('admin.posts.index', array_filter(array_merge(request()->except('page'), $change)));
    $advancedOpen = collect(['featured', 'image', 'author', 'from', 'to'])->contains(fn ($k) => filled($filters[$k] ?? null));
@endphp

@push('styles')
<style>
    .post-thumb { width: 64px; height: 42px; object-fit: cover; border-radius: .3rem; background: var(--tblr-bg-surface-secondary); }
    .post-thumb-empty { display: inline-flex; align-items: center; justify-content: center; color: var(--tblr-secondary); }
    .post-title { max-width: 460px; }
    mark { padding: 0 .1em; }
</style>
@endpush

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        @if ($canCreate)
            <a href="{{ route('admin.posts.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Thêm bài viết</a>
        @endif
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item"><a href="{{ $tabUrl(null) }}" class="nav-link {{ ! $status ? 'active' : '' }}">Tất cả <span class="badge bg-secondary-lt ms-1">{{ $allCount }}</span></a></li>
                <li class="nav-item"><a href="{{ $tabUrl('published') }}" class="nav-link {{ $status === 'published' ? 'active' : '' }}">Đã đăng <span class="badge bg-green-lt ms-1">{{ $statusCounts['published'] ?? 0 }}</span></a></li>
                <li class="nav-item"><a href="{{ $tabUrl('draft') }}" class="nav-link {{ $status === 'draft' ? 'active' : '' }}">Nháp <span class="badge bg-yellow-lt ms-1">{{ $statusCounts['draft'] ?? 0 }}</span></a></li>
            </ul>
        </div>

        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.posts.index') }}" id="post-filters">
                @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif

                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted mb-1">Tìm kiếm</label>
                        <div class="input-icon">
                            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                            <input type="search" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Tiêu đề, đường dẫn, mô tả...">
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small text-muted mb-1">Danh mục</label>
                        <select name="category" class="form-select js-autosubmit">
                            <option value="">Tất cả danh mục</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(($filters['category'] ?? '') == $category->id)>{{ $category->name }} ({{ $category->posts_count }})</option>
                            @endforeach
                            <option value="none" @selected(($filters['category'] ?? '') === 'none')>— Chưa có danh mục —</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1">Sắp xếp</label>
                        <select name="sort" class="form-select js-autosubmit">
                            @foreach ($sorts as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <button class="btn btn-primary flex-fill"><i class="ti ti-filter me-1"></i>Lọc</button>
                        <button type="button" class="btn btn-outline-secondary {{ $advancedOpen ? '' : 'collapsed' }}" data-bs-toggle="collapse" data-bs-target="#advanced-filters" aria-expanded="{{ $advancedOpen ? 'true' : 'false' }}" title="Bộ lọc nâng cao">
                            <i class="ti ti-adjustments-horizontal"></i>
                        </button>
                    </div>
                </div>

                <div class="collapse {{ $advancedOpen ? 'show' : '' }}" id="advanced-filters">
                    <div class="row g-2 mt-1">
                        <div class="col-6 col-md-2">
                            <label class="form-label small text-muted mb-1">Nổi bật</label>
                            <select name="featured" class="form-select js-autosubmit">
                                <option value="">Tất cả</option>
                                <option value="1" @selected(($filters['featured'] ?? '') === '1')>Bài nổi bật</option>
                                <option value="0" @selected(($filters['featured'] ?? '') === '0')>Không nổi bật</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small text-muted mb-1">Ảnh đại diện</label>
                            <select name="image" class="form-select js-autosubmit">
                                <option value="">Tất cả</option>
                                <option value="with" @selected(($filters['image'] ?? '') === 'with')>Có ảnh</option>
                                <option value="without" @selected(($filters['image'] ?? '') === 'without')>Chưa có ảnh</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small text-muted mb-1">Người đăng</label>
                            <select name="author" class="form-select js-autosubmit">
                                <option value="">Tất cả</option>
                                @foreach ($authors as $author)
                                    <option value="{{ $author->id }}" @selected(($filters['author'] ?? '') == $author->id)>{{ $author->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small text-muted mb-1">Đăng từ ngày</label>
                            <input type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small text-muted mb-1">Đến ngày</label>
                            <input type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small text-muted mb-1">Số dòng / trang</label>
                            <select name="per_page" class="form-select js-autosubmit">
                                @foreach ([20, 50, 100] as $n)
                                    <option value="{{ $n }}" @selected((int) ($filters['per_page'] ?? 20) === $n)>{{ $n }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-2 small text-muted">
                        Lọc nhanh:
                        <a href="{{ $filterUrl(['from' => now()->subDays(7)->toDateString(), 'to' => null]) }}">7 ngày qua</a> ·
                        <a href="{{ $filterUrl(['from' => now()->startOfMonth()->toDateString(), 'to' => null]) }}">Tháng này</a> ·
                        <a href="{{ $filterUrl(['from' => now()->startOfYear()->toDateString(), 'to' => null]) }}">Năm nay</a> ·
                        <a href="{{ $filterUrl(['image' => 'without']) }}">Bài thiếu ảnh</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2 small">
            <span class="text-muted">
                @if ($posts->total())
                    Hiển thị {{ $posts->firstItem() }}–{{ $posts->lastItem() }} / <strong>{{ $posts->total() }}</strong> bài viết
                @else
                    Không có bài viết nào
                @endif
            </span>
            @if ($activeFilterCount)
                <span class="badge bg-primary-lt">Đang áp dụng {{ $activeFilterCount }} bộ lọc</span>
                <a href="{{ route('admin.posts.index', $status ? ['status' => $status] : []) }}" class="ms-1"><i class="ti ti-x"></i> Xoá bộ lọc</a>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th style="width: 72px;"></th>
                        <th>Tiêu đề</th>
                        <th>Danh mục</th>
                        <th>Người đăng</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Lượt xem</th>
                        <th>Ngày đăng</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($posts as $post)
                        <tr>
                            <td>
                                @if ($post->image)
                                    <img src="{{ Storage::url($post->image) }}" class="post-thumb" alt="" loading="lazy">
                                @else
                                    <span class="post-thumb post-thumb-empty" title="Chưa có ảnh đại diện"><i class="ti ti-photo-off"></i></span>
                                @endif
                            </td>
                            <td>
                                <div class="post-title fw-medium">
                                    @if ($canEdit)
                                        <a href="{{ route('admin.posts.edit', $post) }}" class="text-reset">{!! filled($filters['q'] ?? null) ? preg_replace('/('.preg_quote(e($filters['q']), '/').')/iu', '<mark>$1</mark>', e($post->title)) : e($post->title) !!}</a>
                                    @else
                                        {{ $post->title }}
                                    @endif
                                    @if ($post->is_featured)
                                        <span class="badge bg-orange-lt ms-1" title="Bài nổi bật"><i class="ti ti-star-filled"></i></span>
                                    @endif
                                </div>
                                <a href="{{ route('public.show', $post->slug) }}" target="_blank" class="small text-muted"><i class="ti ti-external-link"></i> /{{ Str::limit($post->slug, 50) }}</a>
                            </td>
                            <td>
                                @forelse ($post->categories as $category)
                                    <a href="{{ $filterUrl(['category' => $category->id]) }}" class="badge bg-azure-lt text-decoration-none mb-1" title="Lọc theo danh mục này">{{ $category->name }}</a>
                                @empty
                                    <span class="text-muted small">—</span>
                                @endforelse
                            </td>
                            <td class="small">
                                @if ($post->user)
                                    <a href="{{ $filterUrl(['author' => $post->user_id]) }}" class="text-reset" title="Lọc theo người đăng">{{ $post->user->name }}</a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($post->status === 'published')
                                    <span class="badge bg-green-lt">Đã đăng</span>
                                @else
                                    <span class="badge bg-yellow-lt">Nháp</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($post->views) }}</td>
                            <td class="small text-nowrap" title="Cập nhật: {{ $post->updated_at->format('d/m/Y H:i') }}">
                                {{ $post->created_at->format('d/m/Y') }}
                                <div class="text-muted">{{ $post->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="text-nowrap text-end">
                                @if ($canEdit)
                                    <a href="{{ route('admin.posts.edit', $post) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                                @endif
                                @if ($canDelete)
                                    <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" class="d-inline" onsubmit="return confirm('Xoá bài viết này?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Xoá</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="ti ti-search-off fs-1 d-block mb-2"></i>
                                Không tìm thấy bài viết phù hợp.
                                @if ($activeFilterCount)
                                    <a href="{{ route('admin.posts.index') }}">Xoá bộ lọc</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($posts->hasPages())
            <div class="card-footer">{{ $posts->links() }}</div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    // Dropdown filters apply as soon as they change; text and dates wait for "Lọc" / Enter.
    document.querySelectorAll('#post-filters .js-autosubmit').forEach((el) => {
        el.addEventListener('change', () => el.form.requestSubmit());
    });
</script>
@endpush
