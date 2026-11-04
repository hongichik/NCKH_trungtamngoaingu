@extends('layouts.admin')

@section('title', $page->exists ? 'Sửa trang' : 'Thêm trang')

@section('content')
    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" enctype="multipart/form-data" style="max-width: 1000px;">
        @csrf
        {{-- No @method('PUT'): this form has file inputs, and PHP silently drops
             multipart fields (including _token) on a spoofed PUT request. --}}

        <div class="mb-3">
            <label class="form-label">Tiêu đề</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $page->title) }}" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Mô tả ngắn</label>
            <input type="text" name="description" class="form-control" value="{{ old('description', $page->description) }}">
        </div>

        <div class="mb-3">
            <label class="form-label">Nội dung</label>
            <textarea name="content" class="form-control" rows="10" data-rich-editor data-upload-url="{{ route('admin.editor.upload') }}">{{ old('content', $page->content) }}</textarea>
        </div>

        <div style="max-width: 480px;">
            @include('admin.partials.image-field', [
                'name' => 'image',
                'label' => 'Ảnh đại diện',
                'currentUrl' => $page->image ? Storage::url($page->image) : null,
                'aspect' => 16 / 9,
                'removeName' => 'remove_image',
            ])
        </div>

        <div class="mb-3">
            <label class="form-label">Trạng thái</label>
            <select name="status" class="form-select">
                <option value="published" @selected($page->status === 'published')>Đã đăng</option>
                <option value="draft" @selected($page->status === 'draft')>Nháp</option>
            </select>
        </div>

        <button class="btn btn-primary">Lưu</button>
    </form>
@endsection

@include('admin.partials.rich-editor-assets')
