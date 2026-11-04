@extends('layouts.admin')

@section('title', 'Thư viện ảnh')

@section('content')
    @if (auth()->user()->hasPermission('galleries', 'create'))
        <a href="{{ route('admin.galleries.create') }}" class="btn btn-primary mb-3">+ Thêm thư viện ảnh</a>
    @endif

    <table class="table table-bordered bg-white">
        <thead>
            <tr>
                <th>Tên</th>
                <th>Số ảnh</th>
                <th>Trạng thái</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($galleries as $gallery)
                <tr>
                    <td>{{ $gallery->name }}</td>
                    <td>{{ $gallery->images_count }}</td>
                    <td>{{ $gallery->status === 'published' ? 'Đã đăng' : 'Nháp' }}</td>
                    <td class="text-nowrap">
                        @if (auth()->user()->hasPermission('galleries', 'edit'))
                            <a href="{{ route('admin.galleries.edit', $gallery) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                        @endif
                        @if (auth()->user()->hasPermission('galleries', 'delete'))
                            <form method="POST" action="{{ route('admin.galleries.destroy', $gallery) }}" class="d-inline" onsubmit="return confirm('Xoá thư viện ảnh này?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xoá</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $galleries->links() }}
@endsection
