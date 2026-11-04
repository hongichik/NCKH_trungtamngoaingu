@extends('layouts.admin')

@section('title', 'Bài viết')

@section('content')
    @if (auth()->user()->hasPermission('posts', 'create'))
        <a href="{{ route('admin.posts.create') }}" class="btn btn-primary mb-3">+ Thêm bài viết</a>
    @endif

    <table class="table table-bordered bg-white">
        <thead>
            <tr>
                <th>Tiêu đề</th>
                <th>Danh mục</th>
                <th>Trạng thái</th>
                <th>Lượt xem</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($posts as $post)
                <tr>
                    <td>{{ $post->title }}</td>
                    <td>{{ $post->categories->pluck('name')->join(', ') }}</td>
                    <td>{{ $post->status === 'published' ? 'Đã đăng' : 'Nháp' }}</td>
                    <td>{{ $post->views }}</td>
                    <td class="text-nowrap">
                        @if (auth()->user()->hasPermission('posts', 'edit'))
                            <a href="{{ route('admin.posts.edit', $post) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                        @endif
                        @if (auth()->user()->hasPermission('posts', 'delete'))
                            <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" class="d-inline" onsubmit="return confirm('Xoá bài viết này?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xoá</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $posts->links() }}
@endsection
