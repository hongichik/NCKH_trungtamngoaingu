@extends('layouts.admin')

@section('title', 'Danh mục')

@section('content')
    @if (auth()->user()->hasPermission('categories', 'create'))
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary mb-3">+ Thêm danh mục</a>
    @endif

    <table class="table table-bordered bg-white">
        <thead>
            <tr>
                <th>Tên</th>
                <th>Số bài viết</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($categories as $category)
                <tr>
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->posts_count }}</td>
                    <td class="text-nowrap">
                        @if (auth()->user()->hasPermission('categories', 'edit'))
                            <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                        @endif
                        @if (auth()->user()->hasPermission('categories', 'delete'))
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('Xoá danh mục này?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xoá</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $categories->links() }}
@endsection
