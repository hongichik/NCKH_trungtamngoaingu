@extends('layouts.admin')

@section('title', 'Tổng quan')

@section('content')
    @if (empty($stats))
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                Tài khoản của bạn chưa được cấp quyền xem mục nào. Liên hệ admin để được cấp quyền.
            </div>
        </div>
    @else
        <div class="row row-deck row-cards mb-4">
            @foreach ($stats as $stat)
                <div class="col-sm-6 col-lg-4 col-xl-2">
                    <a href="{{ route($stat['route'], $stat['routeParams'] ?? []) }}" class="card card-sm text-decoration-none h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="col-auto">
                                    <span class="bg-{{ $stat['color'] }}-lt avatar">
                                        <i class="ti {{ $stat['icon'] }} fs-3"></i>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fs-2 fw-bold">{{ $stat['count'] }}</div>
                                    <div class="text-muted small">{{ $stat['label'] }}</div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        @if ($examSummary)
            <div class="row row-deck row-cards mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="col-auto">
                                    <span class="bg-yellow-lt avatar"><i class="ti ti-clock-hour-4 fs-3"></i></span>
                                </div>
                                <div class="col">
                                    <div class="fs-2 fw-bold">{{ $examSummary['pending_payment'] }}</div>
                                    <div class="text-muted small">Đăng ký chờ thanh toán</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="col-auto">
                                    <span class="bg-green-lt avatar"><i class="ti ti-cash fs-3"></i></span>
                                </div>
                                <div class="col">
                                    <div class="fs-3 fw-bold">{{ number_format($examSummary['revenue']) }} ₫</div>
                                    <div class="text-muted small">Lệ phí đã thu (đã xác nhận)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($upcomingSessions->isNotEmpty())
            <div class="row row-deck row-cards mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h3 class="card-title mb-0">Kỳ thi sắp diễn ra</h3>
                            <a href="{{ route('admin.exams.index') }}" class="btn btn-sm btn-outline-secondary">Quản lý đợt thi</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Đợt thi</th>
                                        <th>Loại</th>
                                        <th>Ngày thi</th>
                                        <th>Đã đăng ký</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($upcomingSessions as $session)
                                        <tr>
                                            <td>{{ $session->name }}</td>
                                            <td>
                                                <span class="badge {{ $session->exam_type === 'hsk' ? 'bg-indigo-lt' : 'bg-teal-lt' }}">
                                                    {{ $session->exam_type === 'hsk' ? 'HSK' : 'Tin học' }}
                                                </span>
                                            </td>
                                            <td class="text-muted">{{ optional($session->exam_date)->format('d/m/Y') }}</td>
                                            <td>
                                                {{ $session->registrations_count }}{{ $session->capacity !== null ? ' / '.$session->capacity : '' }}
                                                @if ($session->capacity !== null && $session->registrations_count >= $session->capacity)
                                                    <span class="badge bg-red-lt ms-1">Đã đầy</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row row-deck row-cards">
            @if ($postsChart)
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Bài viết đăng theo tháng (6 tháng gần nhất)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="postsChart" height="220"></canvas>
                        </div>
                    </div>
                </div>
            @endif

            @if ($certificatesChart)
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Chứng chỉ cấp theo năm</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="certificatesChart" height="220"></canvas>
                        </div>
                    </div>
                </div>
            @endif

            @if ($recentContacts->isNotEmpty())
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h3 class="card-title mb-0">Liên hệ gần đây</h3>
                            <a href="{{ route('admin.contacts.index') }}" class="btn btn-sm btn-outline-secondary">Xem tất cả</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Họ tên</th>
                                        <th>Tiêu đề</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày gửi</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentContacts as $contact)
                                        <tr>
                                            <td>{{ $contact->name }}</td>
                                            <td class="text-muted">{{ $contact->subject }}</td>
                                            <td>
                                                @if ($contact->status === 'unread') <span class="badge bg-red-lt">Chưa đọc</span>
                                                @elseif ($contact->status === 'replied') <span class="badge bg-green-lt">Đã phản hồi</span>
                                                @else <span class="badge bg-secondary-lt">Đã đọc</span>
                                                @endif
                                            </td>
                                            <td class="text-muted">{{ $contact->created_at->format('d/m/Y') }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('admin.contacts.show', $contact) }}" class="btn btn-sm btn-outline-secondary">Xem</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
@endsection

@push('scripts')
    @if ($postsChart || $certificatesChart)
        <script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

                @if ($postsChart)
                    new Chart(document.getElementById('postsChart'), {
                        type: 'bar',
                        data: {
                            labels: @json($postsChart['labels']),
                            datasets: [{
                                label: 'Bài viết',
                                data: @json($postsChart['data']),
                                backgroundColor: '#0a4a75',
                                borderRadius: 4,
                                maxBarThickness: 40,
                            }],
                        },
                        options: {
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        },
                    });
                @endif

                @if ($certificatesChart)
                    new Chart(document.getElementById('certificatesChart'), {
                        type: 'doughnut',
                        data: {
                            labels: @json($certificatesChart['labels']),
                            datasets: [{
                                data: @json($certificatesChart['data']),
                                backgroundColor: ['#0a4a75', '#ef5823', '#2fb344', '#f59f00', '#4263eb', '#ae3ec9'],
                            }],
                        },
                        options: {
                            plugins: { legend: { position: 'bottom' } },
                        },
                    });
                @endif
            });
        </script>
    @endif
@endpush
