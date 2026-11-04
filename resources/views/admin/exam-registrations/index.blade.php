@extends('layouts.admin')

@section('title', 'Đăng ký dự thi')

@section('content')
    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link {{ $type === 'hsk' ? 'active' : '' }}" href="{{ route('admin.exam-registrations.index', ['type' => 'hsk']) }}">HSK</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $type === 'cntt' ? 'active' : '' }}" href="{{ route('admin.exam-registrations.index', ['type' => 'cntt']) }}">Chứng chỉ Tin học</a>
        </li>
    </ul>

    <form method="GET" class="row g-2 mb-3">
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Tất cả trạng thái</option>
                <option value="pending_payment" @selected(request('status') === 'pending_payment')>Chờ thanh toán</option>
                <option value="paid" @selected(request('status') === 'paid')>Đã thanh toán</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>Đã huỷ</option>
            </select>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Mã đăng ký</th>
                        <th>Thí sinh</th>
                        <th>Đợt thi</th>
                        <th>Cấp độ</th>
                        <th>Lệ phí</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registrations as $registration)
                        <tr>
                            <td><code>{{ $registration->registration_code }}</code></td>
                            <td>
                                {{ $registration->student->full_name }}
                                <div class="text-muted small">{{ $registration->student->phone }} · {{ $registration->student->email }}</div>
                            </td>
                            <td>{{ $registration->examSession->name }}</td>
                            <td>{{ $registration->examLevel->name }}</td>
                            <td>{{ number_format($registration->price) }} ₫</td>
                            <td>
                                @if ($registration->status === 'paid')
                                    <span class="badge bg-green-lt">Đã thanh toán</span>
                                @elseif ($registration->status === 'cancelled')
                                    <span class="badge bg-secondary-lt">Đã huỷ</span>
                                @else
                                    <span class="badge bg-yellow-lt">Chờ thanh toán</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if ($registration->status === 'pending_payment')
                                    <form method="POST" action="{{ route('admin.exam-registrations.confirm-payment', $registration) }}" class="d-inline" onsubmit="return confirm('Xác nhận đã nhận thanh toán?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success">Xác nhận thanh toán</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.exam-registrations.cancel', $registration) }}" class="d-inline" onsubmit="return confirm('Huỷ đăng ký này?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning">Huỷ</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.exam-registrations.destroy', $registration) }}" class="d-inline" onsubmit="return confirm('Xoá vĩnh viễn bản ghi này?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Xoá</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Chưa có đăng ký nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $registrations->links() }}</div>
@endsection
