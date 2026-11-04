@extends('layouts.admin')

@section('title', $page->exists ? 'Sửa trang' : 'Thêm trang')

@section('content')
    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" enctype="multipart/form-data" style="max-width: 800px;">
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
            <textarea name="content" class="form-control" rows="10">{{ old('content', $page->content) }}</textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Ảnh đại diện</label>
            <input type="file" name="image" class="form-control">
            @if ($page->image)
                <img src="{{ Storage::url($page->image) }}" class="mt-2" style="max-height: 100px;">
            @endif
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
