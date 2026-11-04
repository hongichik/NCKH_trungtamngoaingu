@extends('layouts.admin')

@section('title', 'Trang tĩnh')

@section('content')
    @if (auth()->user()->hasPermission('pages', 'create'))
        <a href="{{ route('admin.pages.create') }}" class="btn btn-primary mb-3">+ Thêm trang</a>
    @endif

    <table class="table table-bordered bg-white">
        <thead>
            <tr>
                <th>Tiêu đề</th>
                <th>Trạng thái</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pages as $page)
                <tr>
                    <td>{{ $page->title }}</td>
                    <td>{{ $page->status === 'published' ? 'Đã đăng' : 'Nháp' }}</td>
                    <td class="text-nowrap">
                        @if (auth()->user()->hasPermission('pages', 'edit'))
                            <a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                        @endif
                        @if (auth()->user()->hasPermission('pages', 'delete'))
                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="d-inline" onsubmit="return confirm('Xoá trang này?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xoá</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $pages->links() }}
@endsection
