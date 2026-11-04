@extends('layouts.admin')

@section('title', $gallery->exists ? 'Sửa thư viện ảnh' : 'Thêm thư viện ảnh')

@section('content')
    <form method="POST" action="{{ $gallery->exists ? route('admin.galleries.update', $gallery) : route('admin.galleries.store') }}" enctype="multipart/form-data" style="max-width: 700px;">
        @csrf
        {{-- No @method('PUT'): this form has file inputs, and PHP silently drops
             multipart fields (including _token) on a spoofed PUT request. --}}

        <div class="mb-3">
            <label class="form-label">Tên thư viện ảnh</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $gallery->name) }}" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Mô tả</label>
            <textarea name="description" class="form-control">{{ old('description', $gallery->description) }}</textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Ảnh đại diện</label>
            <input type="file" name="image" class="form-control">
            @if ($gallery->image)
                <img src="{{ Storage::url($gallery->image) }}" class="mt-2" style="max-height: 100px;">
            @endif
        </div>

        <div class="mb-3">
            <label class="form-label">Thêm ảnh vào thư viện</label>
            <input type="file" name="images[]" class="form-control" multiple>
        </div>

        @if ($gallery->exists && $gallery->images->isNotEmpty())
            <div class="row g-2 mb-3">
                @foreach ($gallery->images as $image)
                    <div class="col-2 position-relative">
                        <img src="{{ Storage::url($image->image) }}" class="img-fluid rounded">
                        @if (auth()->user()->hasPermission('galleries', 'delete'))
                            <form method="POST" action="{{ route('admin.galleries.images.destroy', [$gallery, $image]) }}" onsubmit="return confirm('Xoá ảnh này?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger w-100 mt-1">Xoá</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mb-3">
            <label class="form-label">Trạng thái</label>
            <select name="status" class="form-select">
                <option value="published" @selected($gallery->status === 'published')>Đã đăng</option>
                <option value="draft" @selected($gallery->status === 'draft')>Nháp</option>
            </select>
        </div>

        <button class="btn btn-primary">Lưu</button>
    </form>
@endsection
