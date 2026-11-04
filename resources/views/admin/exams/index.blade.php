@extends('layouts.admin')

@section('title', 'Đợt thi & Lệ phí')

@section('content')
    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link {{ $type === 'hsk' ? 'active' : '' }}" href="{{ route('admin.exams.index', ['type' => 'hsk']) }}">HSK</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $type === 'cntt' ? 'active' : '' }}" href="{{ route('admin.exams.index', ['type' => 'cntt']) }}">Chứng chỉ Tin học</a>
        </li>
    </ul>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Cấp độ & Bảng giá</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter mb-0">
                        <thead>
                            <tr>
                                <th>Tên cấp độ</th>
                                <th>Giá (₫)</th>
                                <th>Thứ tự</th>
                                <th>Hiển thị</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($levels as $level)
                                <form id="level-update-{{ $level->id }}" method="POST" action="{{ route('admin.exams.levels.update', $level) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="exam_type" value="{{ $type }}">
                                </form>
                                <form id="level-delete-{{ $level->id }}" method="POST" action="{{ route('admin.exams.levels.destroy', $level) }}" onsubmit="return confirm('Xoá cấp độ này?')">
                                    @csrf @method('DELETE')
                                </form>
                                <tr>
                                    <td><input form="level-update-{{ $level->id }}" type="text" name="name" class="form-control form-control-sm" value="{{ $level->name }}" required></td>
                                    <td><input form="level-update-{{ $level->id }}" type="number" name="price" class="form-control form-control-sm" value="{{ $level->price }}" min="0" required style="max-width: 130px;"></td>
                                    <td><input form="level-update-{{ $level->id }}" type="number" name="order" class="form-control form-control-sm" value="{{ $level->order }}" min="0" style="max-width: 80px;"></td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input form="level-update-{{ $level->id }}" type="checkbox" name="is_active" value="1" class="form-check-input" @checked($level->is_active)>
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <button form="level-update-{{ $level->id }}" class="btn btn-sm btn-outline-primary">Lưu</button>
                                        <button form="level-delete-{{ $level->id }}" class="btn btn-sm btn-outline-danger">Xoá</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <form method="POST" action="{{ route('admin.exams.levels.store') }}" class="row g-2 align-items-end">
                        @csrf
                        <input type="hidden" name="exam_type" value="{{ $type }}">
                        <div class="col">
                            <label class="form-label small mb-1">Tên cấp độ mới</label>
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="Vd: HSK 3" required>
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-1">Giá (₫)</label>
                            <input type="number" name="price" class="form-control form-control-sm" min="0" required style="width: 140px;">
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-sm btn-primary">+ Thêm cấp độ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Đợt thi</h3>
                    <a href="{{ route('admin.exams.sessions.create', ['type' => $type]) }}" class="btn btn-sm btn-primary">+ Thêm đợt thi</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter mb-0">
                        <thead>
                            <tr>
                                <th>Tên đợt thi</th>
                                <th>Ngày thi</th>
                                <th>Đăng ký</th>
                                <th>Trạng thái</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sessions as $session)
                                <tr>
                                    <td>{{ $session->name }}</td>
                                    <td>{{ optional($session->exam_date)->format('d/m/Y') }}</td>
                                    <td>
                                        <a href="{{ route('admin.exam-registrations.index', ['type' => $type]) }}" class="text-decoration-none">
                                            {{ $session->registrations_count }}{{ $session->capacity !== null ? ' / '.$session->capacity : '' }}
                                        </a>
                                        @if ($session->capacity !== null && $session->registrations_count >= $session->capacity)
                                            <span class="badge bg-red-lt ms-1">Đã đầy</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($session->status === 'open')
                                            <span class="badge bg-green-lt">Đang mở</span>
                                        @else
                                            <span class="badge bg-secondary-lt">Đã đóng</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('admin.exams.sessions.edit', $session) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                                        <form method="POST" action="{{ route('admin.exams.sessions.destroy', $session) }}" class="d-inline" onsubmit="return confirm('Xoá đợt thi này?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Xoá</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">Chưa có đợt thi nào.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
