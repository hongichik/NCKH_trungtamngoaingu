@extends('layouts.admin')

@section('title', $certificate->exists ? 'Sửa chứng chỉ' : 'Thêm chứng chỉ')

@section('content')
    <form method="POST" action="{{ $certificate->exists ? route('admin.certificates.update', $certificate) : route('admin.certificates.store') }}" style="max-width: 700px;">
        @csrf
        @if ($certificate->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Học viên</label>
                <select name="student_id" class="form-select" required>
                    <option value="">-- Chọn học viên --</option>
                    @foreach ($students as $student)
                        <option value="{{ $student->id }}" @selected(old('student_id', $certificate->student_id) == $student->id)>{{ $student->full_name }} ({{ $student->identity_number }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Số hiệu chứng chỉ</label>
                <input type="text" name="serial_number" class="form-control" value="{{ old('serial_number', $certificate->serial_number) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Số vào sổ</label>
                <input type="text" name="certificate_number" class="form-control" value="{{ old('certificate_number', $certificate->certificate_number) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Loại chứng chỉ</label>
                <input type="text" name="certificate_type" class="form-control" value="{{ old('certificate_type', $certificate->certificate_type) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Xếp loại</label>
                <input type="text" name="classification" class="form-control" value="{{ old('classification', $certificate->classification) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Ngày thi</label>
                <input type="date" name="exam_date" class="form-control" value="{{ old('exam_date', optional($certificate->exam_date)->format('Y-m-d')) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Nơi thi</label>
                <input type="text" name="exam_place" class="form-control" value="{{ old('exam_place', $certificate->exam_place) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Ngày cấp</label>
                <input type="date" name="issue_date" class="form-control" value="{{ old('issue_date', optional($certificate->issue_date)->format('Y-m-d')) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Nơi cấp</label>
                <input type="text" name="issue_place" class="form-control" value="{{ old('issue_place', $certificate->issue_place) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="published" @selected($certificate->status === 'published')>Hiệu lực</option>
                    <option value="draft" @selected($certificate->status === 'draft')>Ẩn</option>
                </select>
            </div>
        </div>

        <button class="btn btn-primary mt-3">Lưu</button>
    </form>
@endsection
