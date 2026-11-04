@extends('layouts.admin')

@section('title', $student->exists ? 'Sửa học viên' : 'Thêm học viên')

@section('content')
    <form method="POST" action="{{ $student->exists ? route('admin.students.update', $student) : route('admin.students.store') }}" style="max-width: 700px;">
        @csrf
        @if ($student->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Họ</label>
                <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $student->last_name) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Tên</label>
                <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $student->first_name) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Số báo danh</label>
                <input type="text" name="candidate_number" class="form-control" value="{{ old('candidate_number', $student->candidate_number) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">CMND/CCCD</label>
                <input type="text" name="identity_number" class="form-control" value="{{ old('identity_number', $student->identity_number) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Giới tính</label>
                <input type="text" name="gender" class="form-control" value="{{ old('gender', $student->gender) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Ngày sinh</label>
                <input type="date" name="dob" class="form-control" value="{{ old('dob', optional($student->dob)->format('Y-m-d')) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Nơi sinh</label>
                <input type="text" name="birthplace" class="form-control" value="{{ old('birthplace', $student->birthplace) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Điện thoại</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone', $student->phone) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $student->email) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="published" @selected($student->status === 'published')>Hoạt động</option>
                    <option value="draft" @selected($student->status === 'draft')>Ẩn</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Ghi chú</label>
                <textarea name="description" class="form-control">{{ old('description', $student->description) }}</textarea>
            </div>
        </div>

        <button class="btn btn-primary mt-3">Lưu</button>
    </form>
@endsection
