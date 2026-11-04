@extends('layouts.portal')

@section('title', 'Kết quả & Đăng ký của tôi')

@section('content')
    <h1 class="h4 mb-4">Kết quả & Đăng ký của tôi</h1>

    <div class="card mb-4">
        <div class="card-header bg-white">
            <strong>Danh sách đăng ký dự thi</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter mb-0">
                <thead>
                    <tr>
                        <th>Mã đăng ký</th>
                        <th>Loại</th>
                        <th>Đợt thi</th>
                        <th>Cấp độ</th>
                        <th>Lệ phí</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registrations as $registration)
                        <tr>
                            <td><code>{{ $registration->registration_code }}</code></td>
                            <td>
                                @if ($registration->exam_type === 'hsk')
                                    <span class="badge bg-primary">HSK</span>
                                @else
                                    <span class="badge bg-accent" style="background: var(--accent);">Tin học</span>
                                @endif
                            </td>
                            <td>{{ $registration->examSession->name }}</td>
                            <td>{{ $registration->examLevel->name }}</td>
                            <td>{{ number_format($registration->price) }} ₫</td>
                            <td>
                                @if ($registration->status === 'paid')
                                    <span class="badge badge-status-paid">Đã thanh toán</span>
                                @elseif ($registration->status === 'cancelled')
                                    <span class="badge badge-status-cancelled">Đã huỷ</span>
                                @else
                                    <span class="badge badge-status-pending">Chờ thanh toán</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Bạn chưa đăng ký dự thi nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white">
            <strong>Kết quả / Chứng chỉ đã cấp</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter mb-0">
                <thead>
                    <tr>
                        <th>Số hiệu</th>
                        <th>Loại chứng chỉ</th>
                        <th>Xếp loại</th>
                        <th>Ngày cấp</th>
                        <th>Nơi cấp</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($certificates as $certificate)
                        <tr>
                            <td>{{ $certificate->serial_number }}</td>
                            <td>{{ $certificate->certificate_type }}</td>
                            <td>{{ $certificate->classification ?: '—' }}</td>
                            <td>{{ optional($certificate->issue_date)->format('d/m/Y') }}</td>
                            <td>{{ $certificate->issue_place }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Bạn chưa có kết quả / chứng chỉ nào được cấp.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-muted small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        Bạn cũng có thể <a href="{{ route('certificates.lookup') }}" target="_blank">tra cứu chứng chỉ theo số hiệu</a> tại trang công khai của trung tâm.
    </p>
@endsection
