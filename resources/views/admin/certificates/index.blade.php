@extends('layouts.admin')

@section('title', 'Chứng chỉ')

@section('content')
    <div class="d-flex justify-content-between mb-3">
        @if (auth()->user()->hasPermission('certificates', 'create'))
            <a href="{{ route('admin.certificates.create') }}" class="btn btn-primary">+ Thêm chứng chỉ</a>
        @else
            <span></span>
        @endif
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="q" class="form-control" placeholder="Tìm theo số hiệu" value="{{ request('q') }}">
            <button class="btn btn-outline-secondary">Tìm</button>
        </form>
    </div>

    <table class="table table-bordered bg-white">
        <thead>
            <tr>
                <th>Học viên</th>
                <th>Số hiệu</th>
                <th>Loại</th>
                <th>Xếp loại</th>
                <th>Ngày cấp</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($certificates as $certificate)
                <tr>
                    <td>{{ $certificate->student?->full_name }}</td>
                    <td>{{ $certificate->serial_number }}</td>
                    <td>{{ $certificate->certificate_type }}</td>
                    <td>{{ $certificate->classification }}</td>
                    <td>{{ optional($certificate->issue_date)->format('d/m/Y') }}</td>
                    <td class="text-nowrap">
                        @if (auth()->user()->hasPermission('certificates', 'edit'))
                            <a href="{{ route('admin.certificates.edit', $certificate) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                        @endif
                        @if (auth()->user()->hasPermission('certificates', 'delete'))
                            <form method="POST" action="{{ route('admin.certificates.destroy', $certificate) }}" class="d-inline" onsubmit="return confirm('Xoá chứng chỉ này?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xoá</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $certificates->links() }}
@endsection
