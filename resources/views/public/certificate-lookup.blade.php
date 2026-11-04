@extends('layouts.app')

@section('title', 'Tra cứu chứng chỉ')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="text-center mb-4">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background: rgba(10,74,117,.1);">
                        <i class="bi bi-patch-check-fill text-brand fs-3"></i>
                    </div>
                    <h1 class="h3 mb-2">Tra cứu chứng chỉ</h1>
                    <p class="text-muted mb-0">Nhập số hiệu chứng chỉ để kiểm tra thông tin đã được cấp.</p>
                </div>

                <div class="card shadow-soft border-0 mb-4">
                    <div class="card-body p-4">
                        <form method="GET" class="row g-2">
                            <div class="col-sm-9">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" name="serial_number" class="form-control" placeholder="Nhập số hiệu chứng chỉ" value="{{ $serialNumber }}">
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <button class="btn btn-primary btn-lg w-100">Tra cứu</button>
                            </div>
                        </form>
                    </div>
                </div>

                @if ($serialNumber)
                    @if ($certificate)
                        <div class="card shadow-soft border-start border-4 border-success" data-aos="fade-up">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center gap-2 mb-4">
                                    <i class="bi bi-check-circle-fill text-success fs-4"></i>
                                    <h5 class="card-title mb-0 text-success">Chứng chỉ hợp lệ</h5>
                                </div>
                                <div class="row gy-3">
                                    <div class="col-sm-6">
                                        <div class="text-muted small">Họ tên</div>
                                        <div class="fw-semibold">{{ $certificate->student?->full_name }}</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="text-muted small">Số hiệu</div>
                                        <div class="fw-semibold">{{ $certificate->serial_number }}</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="text-muted small">Loại chứng chỉ</div>
                                        <div class="fw-semibold">{{ $certificate->certificate_type }}</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="text-muted small">Xếp loại</div>
                                        <div class="fw-semibold">{{ $certificate->classification }}</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="text-muted small">Ngày cấp</div>
                                        <div class="fw-semibold">{{ optional($certificate->issue_date)->format('d/m/Y') }}</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="text-muted small">Nơi cấp</div>
                                        <div class="fw-semibold">{{ $certificate->issue_place }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="card shadow-soft border-start border-4 border-warning" data-aos="fade-up">
                            <div class="card-body p-4 d-flex align-items-center gap-3">
                                <i class="bi bi-exclamation-triangle-fill text-warning fs-3"></i>
                                <div>
                                    <div class="fw-semibold">Không tìm thấy chứng chỉ</div>
                                    <div class="text-muted small">Không có chứng chỉ nào khớp với số hiệu "{{ $serialNumber }}". Vui lòng kiểm tra lại hoặc liên hệ trung tâm để được hỗ trợ.</div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
