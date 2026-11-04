@extends('layouts.admin')

@section('title', 'Học viên')

@section('content')
    <div class="d-flex justify-content-between mb-3">
        @if (auth()->user()->hasPermission('students', 'create'))
            <a href="{{ route('admin.students.create') }}" class="btn btn-primary">+ Thêm học viên</a>
        @else
            <span></span>
        @endif
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="q" class="form-control" placeholder="Tìm theo tên, CMND/CCCD" value="{{ request('q') }}">
            <button class="btn btn-outline-secondary">Tìm</button>
        </form>
    </div>

    <table class="table table-bordered bg-white">
        <thead>
            <tr>
                <th>Họ tên</th>
                <th>CMND/CCCD</th>
                <th>Ngày sinh</th>
                <th>Trạng thái</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($students as $student)
                <tr>
                    <td>{{ $student->full_name }}</td>
                    <td>{{ $student->identity_number }}</td>
                    <td>{{ optional($student->dob)->format('d/m/Y') }}</td>
                    <td>{{ $student->status === 'published' ? 'Hoạt động' : 'Ẩn' }}</td>
                    <td class="text-nowrap">
                        @if (auth()->user()->hasPermission('students', 'edit'))
                            <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                        @endif
                        @if (auth()->user()->hasPermission('students', 'delete'))
                            <form method="POST" action="{{ route('admin.students.destroy', $student) }}" class="d-inline" onsubmit="return confirm('Xoá học viên này?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xoá</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $students->links() }}
@endsection
