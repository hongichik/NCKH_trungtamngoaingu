@extends('layouts.admin')

@section('title', $category->exists ? 'Sửa danh mục' : 'Thêm danh mục')

@section('content')
    <form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" style="max-width: 600px;">
        @csrf
        @if ($category->exists) @method('PUT') @endif

        <div class="mb-3">
            <label class="form-label">Tên danh mục</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $category->name) }}" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Mô tả</label>
            <textarea name="description" class="form-control">{{ old('description', $category->description) }}</textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Thứ tự</label>
            <input type="number" name="order" class="form-control" value="{{ old('order', $category->order) }}">
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="is_featured" class="form-check-input" id="is_featured" value="1" @checked($category->is_featured)>
            <label class="form-check-label" for="is_featured">Nổi bật</label>
        </div>

        <button class="btn btn-primary">Lưu</button>
    </form>
@endsection
