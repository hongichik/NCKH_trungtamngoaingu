@extends('layouts.admin')

@section('title', $post->exists ? 'Sửa bài viết' : 'Thêm bài viết')

@section('content')
    <form method="POST" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}" enctype="multipart/form-data" id="post-form">
        @csrf
        {{-- No @method('PUT'): this form has file inputs, and PHP silently drops
             multipart fields (including _token) on a spoofed PUT request. --}}

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required">Tiêu đề</label>
                            <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $post->title) }}" required>
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Đường dẫn (slug)</label>
                            <div class="input-group">
                                <span class="input-group-text text-muted">{{ url('/') }}/</span>
                                <input type="text" name="slug" id="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $post->slug) }}" placeholder="tu-dong-tao-tu-tieu-de">
                                <button type="button" class="btn btn-outline-secondary" id="regenerate-slug" title="Tạo lại từ tiêu đề">
                                    <i class="ti ti-refresh"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Để trống để tự động tạo từ tiêu đề. Chỉ tạo lại nếu bạn xoá trắng hoặc bấm nút làm mới.</small>
                            @error('slug') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Mô tả ngắn</label>
                            <textarea name="description" class="form-control" rows="3" maxlength="400">{{ old('description', $post->description) }}</textarea>
                            <small class="form-text text-muted">Hiển thị ở danh sách bài viết và mô tả tìm kiếm.</small>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Nội dung bài viết</h3>
                    </div>
                    <div class="card-body">
                        <textarea name="content" id="content" class="form-control" rows="20" data-rich-editor data-upload-url="{{ route('admin.editor.upload') }}">{{ old('content', $post->content) }}</textarea>
                        <div class="form-hint mt-2"><i class="ti ti-info-circle me-1"></i>Kéo thả hoặc dán ảnh trực tiếp vào nội dung. Bấm vào ảnh để chỉnh kích thước (kéo góc ảnh), căn trái/phải cho chữ bao quanh, thêm chú thích hoặc gắn link.</div>
                        @error('content') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                @if ($post->exists)
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Ảnh khác trong bài viết</h3>
                        </div>
                        <div class="card-body">
                            @if ($post->images->isNotEmpty())
                                <div class="row g-2 mb-3">
                                    @foreach ($post->images as $image)
                                        <div class="col-6 col-md-3">
                                            <div class="card">
                                                <img src="{{ Storage::url($image->image) }}" class="card-img-top" style="height: 100px; object-fit: cover;">
                                                <div class="card-body p-2">
                                                    {{-- Submits the standalone form below: forms can't be nested inside the post form. --}}
                                                    <button type="submit" form="delete-image-{{ $image->id }}" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Xoá ảnh này?')">Xoá</button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <label class="form-label">Thêm ảnh mới</label>
                            <input type="file" name="gallery_images[]" class="form-control" multiple accept="image/*">
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Xuất bản</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" class="form-select">
                                <option value="published" @selected(old('status', $post->status) === 'published')>Đã đăng</option>
                                <option value="draft" @selected(old('status', $post->status) === 'draft')>Nháp</option>
                            </select>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_featured" class="form-check-input" id="is_featured" value="1" @checked(old('is_featured', $post->is_featured))>
                            <label class="form-check-label" for="is_featured">Bài viết nổi bật</label>
                        </div>

                        @if ($post->exists)
                            <div class="border-top pt-3 mt-3 small text-muted">
                                <div class="mb-1">Tạo lúc: {{ $post->created_at->format('d/m/Y H:i') }}</div>
                                <div class="mb-1">Cập nhật: {{ $post->updated_at->format('d/m/Y H:i') }}</div>
                                <div>Lượt xem: {{ number_format($post->views) }}</div>
                            </div>
                        @endif

                        <div class="mt-3 d-grid gap-2">
                            <button class="btn btn-primary">Lưu</button>
                            <a href="{{ route('admin.posts.index') }}" class="btn btn-link">Huỷ</a>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Danh mục</h3>
                    </div>
                    <div class="card-body">
                        @forelse ($categories as $category)
                            <div class="form-check">
                                <input type="checkbox" name="categories[]" class="form-check-input" id="category-{{ $category->id }}" value="{{ $category->id }}" @checked($post->categories->contains($category))>
                                <label class="form-check-label" for="category-{{ $category->id }}">{{ $category->name }}</label>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">Chưa có danh mục nào.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Ảnh đại diện</h3>
                    </div>
                    <div class="card-body">
                        @include('admin.partials.image-field', [
                            'name' => 'image',
                            'currentUrl' => $post->image ? Storage::url($post->image) : null,
                            'aspect' => 16 / 9,
                            'removeName' => 'remove_image',
                            'hint' => 'Tỉ lệ khuyên dùng 16:9. Sau khi chọn ảnh có thể cắt, xoay, phóng to.',
                        ])
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if ($post->exists)
        @foreach ($post->images as $image)
            <form method="POST" action="{{ route('admin.posts.images.destroy', [$post, $image]) }}" id="delete-image-{{ $image->id }}" class="d-none">
                @csrf @method('DELETE')
            </form>
        @endforeach
    @endif
@endsection

@include('admin.partials.rich-editor-assets')

@push('styles')
    <style>
        .required:after { content: " *"; color: var(--tblr-danger); }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Slug: auto from title unless the user has typed into the slug field themselves.
            var titleInput = document.getElementById('title');
            var slugInput = document.getElementById('slug');
            var slugManuallyEdited = slugInput.value.trim() !== '';

            function slugify(text) {
                var map = { à:'a',á:'a',ạ:'a',ả:'a',ã:'a',â:'a',ầ:'a',ấ:'a',ậ:'a',ẩ:'a',ẫ:'a',ă:'a',ằ:'a',ắ:'a',ặ:'a',ẳ:'a',ẵ:'a',è:'e',é:'e',ẹ:'e',ẻ:'e',ẽ:'e',ê:'e',ề:'e',ế:'e',ệ:'e',ể:'e',ễ:'e',ì:'i',í:'i',ị:'i',ỉ:'i',ĩ:'i',ò:'o',ó:'o',ọ:'o',ỏ:'o',õ:'o',ô:'o',ồ:'o',ố:'o',ộ:'o',ổ:'o',ỗ:'o',ơ:'o',ờ:'o',ớ:'o',ợ:'o',ở:'o',ỡ:'o',ù:'u',ú:'u',ụ:'u',ủ:'u',ũ:'u',ư:'u',ừ:'u',ứ:'u',ự:'u',ử:'u',ữ:'u',ỳ:'y',ý:'y',ỵ:'y',ỷ:'y',ỹ:'y',đ:'d' };
                return text.toLowerCase().split('').map(function (ch) { return map[ch] || ch; }).join('')
                    .replace(/[^a-z0-9\s-]/g, '')
                    .trim()
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
            }

            titleInput.addEventListener('input', function () {
                if (!slugManuallyEdited) {
                    slugInput.value = slugify(titleInput.value);
                }
            });

            slugInput.addEventListener('input', function () {
                slugManuallyEdited = slugInput.value.trim() !== '';
            });

            document.getElementById('regenerate-slug').addEventListener('click', function () {
                slugInput.value = slugify(titleInput.value);
                slugManuallyEdited = false;
            });
        });
    </script>
@endpush
