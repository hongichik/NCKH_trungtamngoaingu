@extends('layouts.admin')

@section('title', $session->exists ? 'Sửa đợt thi' : 'Thêm đợt thi')

@section('content')
    <form method="POST" action="{{ $session->exists ? route('admin.exams.sessions.update', $session) : route('admin.exams.sessions.store') }}" style="max-width: 600px;">
        @csrf
        @if ($session->exists) @method('PUT') @endif

        <input type="hidden" name="exam_type" value="{{ $type }}">

        <div class="mb-3">
            <label class="form-label">Loại kỳ thi</label>
            <input type="text" class="form-control" value="{{ $type === 'hsk' ? 'HSK' : 'Chứng chỉ ứng dụng CNTT' }}" disabled>
        </div>

        <div class="mb-3">
            <label class="form-label">Tên đợt thi</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $session->name) }}" placeholder="Vd: Đợt thi 13.12.2026" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Ngày thi</label>
            <input type="date" name="exam_date" class="form-control" value="{{ old('exam_date', optional($session->exam_date)->format('Y-m-d')) }}">
        </div>

        <div class="mb-3">
            <label class="form-label">Giới hạn số lượng đăng ký</label>
            <input type="number" name="capacity" min="0" class="form-control" value="{{ old('capacity', $session->capacity) }}" placeholder="Để trống nếu không giới hạn">
            @if ($session->exists)
                <small class="form-text text-muted">Hiện có {{ $session->activeRegistrations()->count() }} thí sinh đã đăng ký.</small>
            @endif
        </div>

        <div class="row">
            <div class="col-6 mb-3">
                <label class="form-label">Bắt đầu đăng ký</label>
                <input type="date" name="registration_start" class="form-control" value="{{ old('registration_start', optional($session->registration_start)->format('Y-m-d')) }}">
            </div>
            <div class="col-6 mb-3">
                <label class="form-label">Kết thúc đăng ký</label>
                <input type="date" name="registration_end" class="form-control" value="{{ old('registration_end', optional($session->registration_end)->format('Y-m-d')) }}">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Trạng thái</label>
            <select name="status" class="form-select">
                <option value="open" @selected(old('status', $session->status) === 'open')>Đang mở đăng ký</option>
                <option value="closed" @selected(old('status', $session->status) === 'closed')>Đã đóng</option>
            </select>
        </div>

        <button class="btn btn-primary">Lưu</button>
        <a href="{{ route('admin.exams.index', ['type' => $type]) }}" class="btn btn-link">Huỷ</a>
    </form>
@endsection
