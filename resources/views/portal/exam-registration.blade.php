@extends('layouts.portal')

@section('title', 'Đăng ký thi ' . $typeName)

@section('content')
    <h1 class="h4 mb-4">Đăng ký thi {{ $typeName }}</h1>

    @if (!$student->hasCompletedCoreProfile() || ($type === 'hsk' && !$student->hasCompletedHskProfile()))
        <div class="alert alert-warning d-flex flex-column flex-sm-row align-items-sm-center justify-content-sm-between gap-2">
            <div>
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                Bạn cần bổ sung đầy đủ thông tin thí sinh{{ $type === 'hsk' ? ' (bao gồm mục dành riêng cho HSK)' : '' }} trước khi đăng ký dự thi.
            </div>
            <a href="{{ route('portal.profile.edit', ['type' => $type]) }}" class="btn btn-sm btn-warning text-nowrap align-self-sm-center">Bổ sung ngay</a>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('portal.exam.store', ['type' => $type]) }}">
                @csrf

                <div class="mb-4">
                    <div class="fw-semibold mb-2">Chọn đợt thi <span class="text-danger">*</span></div>
                    @php $availableSessions = $sessions->filter(fn ($s) => $s->capacity === null || $s->registrations_count < $s->capacity); @endphp
                    @forelse ($sessions as $session)
                        @php $isFull = $session->capacity !== null && $session->registrations_count >= $session->capacity; @endphp
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="exam_session_id" id="session-{{ $session->id }}" value="{{ $session->id }}"
                                required @checked($availableSessions->count() === 1 && !$isFull) @disabled($isFull)>
                            <label class="form-check-label {{ $isFull ? 'text-muted' : '' }}" for="session-{{ $session->id }}">
                                {{ $session->name }}
                                @if ($session->exam_date) — Ngày thi {{ $session->exam_date->format('d/m/Y') }} @endif
                                @if ($session->registration_start && $session->registration_end)
                                    <span class="text-muted small">(Đăng ký {{ $session->registration_start->format('d/m/Y') }} - {{ $session->registration_end->format('d/m/Y') }})</span>
                                @endif
                                @if ($session->capacity !== null)
                                    <span class="badge {{ $isFull ? 'bg-danger' : 'bg-secondary' }} ms-1">
                                        {{ $isFull ? 'Đã đủ số lượng' : $session->registrations_count.'/'.$session->capacity.' chỗ' }}
                                    </span>
                                @endif
                            </label>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Hiện chưa có đợt thi nào đang mở đăng ký. Vui lòng quay lại sau.</p>
                    @endforelse
                </div>

                @if ($levels->isNotEmpty())
                    <div class="mb-4">
                        <div class="fw-semibold mb-2">Chọn cấp độ <span class="text-danger">*</span></div>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;"></th>
                                        <th>Cấp độ</th>
                                        <th class="text-end">Lệ phí</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($levels as $level)
                                        <tr>
                                            <td class="text-center">
                                                <input class="form-check-input" type="radio" name="exam_level_id" value="{{ $level->id }}" required @checked($levels->count() === 1)>
                                            </td>
                                            <td>{{ $level->name }}</td>
                                            <td class="text-end">{{ number_format($level->price) }} ₫</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="alert alert-light border small">
                    <strong>Lưu ý:</strong><br>
                    - Sau khi đăng ký, vui lòng chuyển khoản lệ phí thi theo thông tin bên dưới để giữ chỗ.<br>
                    - Trung tâm sẽ xác nhận thanh toán trong vòng 1-2 ngày làm việc.<br>
                    - Nội dung chuyển khoản: <em>Họ tên - Số CCCD - Mã đăng ký</em>.
                </div>

                <button class="btn btn-primary" {{ $sessions->isEmpty() || $levels->isEmpty() ? 'disabled' : '' }}>
                    <i class="bi bi-check2-square me-1"></i> Đăng ký dự thi
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <strong>Danh sách đăng ký của bạn</strong>
            <a href="{{ route('portal.results.index') }}" class="btn btn-sm btn-outline-secondary">Xem tất cả & kết quả</a>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter mb-0">
                <thead>
                    <tr>
                        <th>Mã đăng ký</th>
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
                        <tr><td colspan="5" class="text-center text-muted py-4">Không có dữ liệu</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
