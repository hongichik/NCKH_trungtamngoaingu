{{-- Quick actions: register for an exam or look up a certificate --}}
<a href="{{ route('portal.auth', ['type' => 'hsk']) }}" class="text-decoration-none">
    <div class="card shadow-soft border-0 p-3 d-flex flex-row align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; background: rgba(10,74,117,.1);">
            <i class="bi bi-translate text-brand fs-5"></i>
        </div>
        <div>
            <div class="fw-semibold text-body">Đăng ký thi HSK</div>
            <div class="text-muted small">Đăng ký dự thi chứng chỉ tiếng Trung HSK</div>
        </div>
    </div>
</a>

<a href="{{ route('portal.auth', ['type' => 'cntt']) }}" class="text-decoration-none">
    <div class="card shadow-soft border-0 p-3 d-flex flex-row align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; background: rgba(239,88,35,.1);">
            <i class="bi bi-laptop text-accent fs-5"></i>
        </div>
        <div>
            <div class="fw-semibold text-body">Đăng ký thi Chứng chỉ Tin học</div>
            <div class="text-muted small">Đăng ký dự thi chứng chỉ ứng dụng CNTT</div>
        </div>
    </div>
</a>

<a href="{{ route('certificates.lookup') }}" class="text-decoration-none">
    <div class="card shadow-soft border-0 p-3 d-flex flex-row align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; background: rgba(10,74,117,.1);">
            <i class="bi bi-search text-brand fs-5"></i>
        </div>
        <div>
            <div class="fw-semibold text-body">Tra cứu chứng chỉ</div>
            <div class="text-muted small">Kiểm tra thông tin chứng chỉ đã cấp</div>
        </div>
    </div>
</a>
