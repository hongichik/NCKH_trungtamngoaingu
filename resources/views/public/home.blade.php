@extends('layouts.app')

@section('title', 'Trang chủ')
@section('meta_description', 'Trung tâm Ngoại ngữ - Tin học, Trường Đại học Hạ Long: đào tạo, luyện thi và cấp chứng chỉ ngoại ngữ, tin học, HSK uy tín. Tra cứu chứng chỉ trực tuyến, xem tin tức tuyển sinh và thư viện ảnh hoạt động.')
@section('og_image', optional($heroPosts->first())->image ? Storage::url($heroPosts->first()->image) : asset('images/logo.jpg'))

@section('content')
    {{-- Hero: full-width banner, text sinks over the background (color placeholder until real banner photos exist) --}}
    <section class="hero-section">
        <div class="swiper hero-swiper">
            <div class="swiper-wrapper">
                {{-- Slide 1: general intro (trạng thái chung / mặc định) --}}
                <div class="swiper-slide">
                    <div class="hero-slide" style="background: linear-gradient(135deg, var(--brand), var(--brand-dark));">
                        <div class="hero-slide-overlay"></div>
                        <div class="container position-relative">
                            <div class="hero-slide-content text-white" data-aos="fade-up">
                                <span class="badge bg-white text-brand mb-3 fw-semibold">Trực thuộc Trường Đại học Hạ Long</span>
                                <h1 class="display-5 fw-bold text-white mb-3">{{ config('app.name') }}</h1>
                                <p class="lead mb-4" style="max-width: 620px; opacity: .92;">Đào tạo, luyện thi và cấp chứng chỉ ngoại ngữ, tin học, HSK chuẩn đầu ra. Tra cứu chứng chỉ trực tuyến nhanh chóng, chính xác.</p>
                                <div class="d-flex flex-wrap gap-3">
                                    <a href="tel:0974024655" class="btn btn-light btn-lg fw-semibold text-brand">
                                        <i class="bi bi-telephone me-2"></i>Hotline 0974 024 655
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Slide 2: tuyển sinh promo --}}
                <div class="swiper-slide">
                    <div class="hero-slide" style="background: linear-gradient(135deg, var(--accent), var(--brand));">
                        <div class="hero-slide-overlay"></div>
                        <div class="container position-relative">
                            <div class="hero-slide-content text-white">
                                <span class="badge bg-white text-accent mb-3 fw-semibold">Tuyển sinh</span>
                                <h2 class="display-6 fw-bold text-white mb-3">Tuyển sinh các lớp Ngoại ngữ &middot; Tin học &middot; HSK</h2>
                                <ul class="list-unstyled mb-4">
                                    <li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i>Luyện thi chứng chỉ ứng dụng CNTT</li>
                                    <li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i>Luyện thi chứng chỉ tiếng Trung quốc tế HSK</li>
                                    <li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i>Tiếng Anh, tiếng Nhật, tiếng Hàn mọi trình độ</li>
                                </ul>
                                <a href="tel:0974024655" class="btn btn-light fw-semibold">
                                    <i class="bi bi-telephone me-2"></i>Đăng ký ngay: 0974 024 655
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Slide 3: real enrollment poster (banner template: always full width, height follows the image ratio) --}}
                <div class="swiper-slide">
                    <img src="{{ Storage::url('slideshow/hero-image-tuyen-sinh-3-2.jpg') }}" alt="Thông báo tuyển sinh các lớp Ngoại ngữ - Tin học - HSK" class="hero-slide-poster-img" loading="lazy">
                </div>
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </section>

    {{-- 3 key actions --}}
    <section class="py-5">
        <div class="container">
            <div class="row g-3 g-lg-4" data-aos="fade-up">
                <div class="col-md-4">
                    <a href="{{ route('certificates.lookup') }}" class="text-decoration-none">
                        <div class="card h-100 shadow-soft border-0 p-4 d-flex flex-row align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px; background: rgba(10,74,117,.1);">
                                <i class="bi bi-search text-brand fs-4"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-body">Tra cứu chứng chỉ</div>
                                <div class="text-muted small">Kiểm tra thông tin chứng chỉ đã cấp</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('portal.auth', ['type' => 'cntt']) }}" class="text-decoration-none">
                        <div class="card h-100 shadow-soft border-0 p-4 d-flex flex-row align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px; background: rgba(239,88,35,.1);">
                                <i class="bi bi-laptop text-accent fs-4"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-body">Đăng ký chứng chỉ CNTT</div>
                                <div class="text-muted small">Đăng ký luyện thi &amp; thi chứng chỉ tin học</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('portal.auth', ['type' => 'hsk']) }}" class="text-decoration-none">
                        <div class="card h-100 shadow-soft border-0 p-4 d-flex flex-row align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px; background: rgba(10,74,117,.1);">
                                <i class="bi bi-translate text-brand fs-4"></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-body">Đăng ký chứng chỉ HSK</div>
                                <div class="text-muted small">Đăng ký luyện thi &amp; thi tiếng Trung HSK</div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>


    {{-- Lĩnh vực đào tạo --}}
    <section class="py-5 bg-white">
        <div class="container">
            <div class="mb-4 text-center" data-aos="fade-up">
                <h2 class="h3 mb-1">Lĩnh vực đào tạo</h2>
                <p class="text-muted mb-0">3 lĩnh vực đào tạo chính của trung tâm</p>
            </div>
            @php
                $fields = [
                    ['icon' => 'bi-laptop', 'title' => 'Chứng chỉ Tin học (CNTT)', 'desc' => 'Đào tạo và tổ chức thi chứng chỉ ứng dụng công nghệ thông tin theo chuẩn đầu ra.'],
                    ['icon' => 'bi-translate', 'title' => 'Ngoại ngữ', 'desc' => 'Đào tạo tiếng Anh, tiếng Nhật, tiếng Hàn cho mọi trình độ, từ mất gốc đến nâng cao.'],
                    ['icon' => 'bi-mortarboard', 'title' => 'Chứng chỉ HSK', 'desc' => 'Đào tạo và tổ chức thi chứng chỉ tiếng Trung Quốc tế HSK (HSK3 - HSK5).'],
                ];
            @endphp
            <div class="row g-4">
                @foreach ($fields as $i => $field)
                    <div class="col-md-4" data-aos="fade-up" data-aos-delay="{{ $i * 100 }}">
                        <div class="card h-100 text-center p-4 border-0 shadow-soft">
                            <i class="bi {{ $field['icon'] }} text-brand mb-3" style="font-size: 2.2rem;"></i>
                            <div class="fw-semibold text-body mb-2">{{ $field['title'] }}</div>
                            <p class="text-muted small mb-0">{{ $field['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Latest news, grouped by category --}}
    {{-- Desktop/tablet: one stacked section per category --}}
    <section class="py-5 d-none d-md-block">
        <div class="container">
            @foreach ($newsCategories as $category)
                <div class="d-flex justify-content-between align-items-end mb-4" data-aos="fade-up">
                    <h2 class="h4 mb-0">{{ $category->name }}</h2>
                    <a href="{{ route('category.show', $category->slug) }}" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
                </div>
                <div class="row g-4 {{ !$loop->last ? 'mb-5' : '' }}">
                    @foreach ($category->recentPosts as $i => $post)
                        <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="{{ ($i % 4) * 80 }}">
                            @include('public.partials.post-card', ['post' => $post])
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    {{-- Mobile: compact tabs --}}
    <section class="py-5 d-md-none">
        <div class="container">
            <ul class="nav nav-pills mb-4 d-grid gap-2 news-tabs-grid" id="newsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active w-100 h-100" id="tab-all-btn" data-bs-toggle="pill" data-bs-target="#tab-all" type="button" role="tab" aria-selected="true">Tất cả</button>
                </li>
                @foreach ($newsCategories as $category)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link w-100 h-100" id="tab-cat-{{ $category->id }}-btn" data-bs-toggle="pill" data-bs-target="#tab-cat-{{ $category->id }}" type="button" role="tab" aria-selected="false">{{ $category->name }}</button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content" id="newsTabsContent">
                <div class="tab-pane fade show active" id="tab-all" role="tabpanel">
                    <div class="row g-4">
                        @foreach ($latestPosts as $post)
                            <div class="col-6">
                                @include('public.partials.post-card', ['post' => $post])
                            </div>
                        @endforeach
                    </div>
                </div>
                @foreach ($newsCategories as $category)
                    <div class="tab-pane fade" id="tab-cat-{{ $category->id }}" role="tabpanel">
                        <div class="row g-4">
                            @foreach ($category->recentPosts as $post)
                                <div class="col-6">
                                    @include('public.partials.post-card', ['post' => $post])
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Why choose us --}}
    <section class="py-5 bg-white">
        <div class="container">
            <div class="mb-4 text-center" data-aos="fade-up">
                <h2 class="h3 mb-1">Vì sao chọn {{ config('app.name') }}</h2>
                <p class="text-muted mb-0">Đơn vị đào tạo trực thuộc Trường Đại học Hạ Long</p>
            </div>
            @php
                $reasons = [
                    ['icon' => 'bi-patch-check', 'title' => 'Trực thuộc trường đại học', 'desc' => 'Chứng chỉ được cấp bởi đơn vị trực thuộc Trường Đại học Hạ Long, đảm bảo giá trị pháp lý.'],
                    ['icon' => 'bi-person-workspace', 'title' => 'Đội ngũ giảng viên', 'desc' => 'Giảng viên giàu kinh nghiệm, tận tâm đồng hành cùng học viên trong suốt khóa học.'],
                    ['icon' => 'bi-search-heart', 'title' => 'Tra cứu minh bạch', 'desc' => 'Hệ thống tra cứu chứng chỉ trực tuyến, kiểm tra thông tin nhanh chóng, chính xác.'],
                    ['icon' => 'bi-calendar2-check', 'title' => 'Lịch thi rõ ràng', 'desc' => 'Thông tin tuyển sinh, lịch thi và kết quả được cập nhật thường xuyên trên website.'],
                    ['icon' => 'bi-building', 'title' => 'Cơ sở vật chất', 'desc' => 'Phòng học và phòng thi đạt chuẩn trong khuôn viên Trường Đại học Hạ Long.'],
                    ['icon' => 'bi-headset', 'title' => 'Hỗ trợ tận tâm', 'desc' => 'Đội ngũ tư vấn sẵn sàng hỗ trợ học viên trước, trong và sau khóa học.'],
                ];
            @endphp
            <div class="row g-4">
                @foreach ($reasons as $i => $reason)
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ ($i % 3) * 80 }}">
                        <div class="d-flex gap-3 p-4 h-100 rounded-4 shadow-soft bg-white">
                            <i class="bi {{ $reason['icon'] }} text-accent flex-shrink-0" style="font-size: 1.8rem;"></i>
                            <div>
                                <div class="fw-semibold mb-1">{{ $reason['title'] }}</div>
                                <p class="text-muted small mb-0">{{ $reason['desc'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Address & contact --}}
    <section class="py-5">
        <div class="container">
            <div class="mb-4" data-aos="fade-up">
                <h2 class="h3 mb-1">Địa chỉ &amp; Liên hệ</h2>
                <p class="text-muted mb-0">Ghé thăm trung tâm hoặc liên hệ trực tiếp để được tư vấn</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-5" data-aos="fade-right">
                    <div class="card h-100 border-0 shadow-soft p-4">
                        <div class="d-flex gap-3 mb-4">
                            <i class="bi bi-geo-alt-fill text-accent fs-4 flex-shrink-0"></i>
                            <div>
                                <div class="fw-semibold mb-1">Địa chỉ</div>
                                <p class="text-muted small mb-0">{{ config('site.contact.address') }}</p>
                            </div>
                        </div>
                        @foreach (config('site.contact.staff') as $staff)
                            <div class="d-flex gap-3 mb-4">
                                <i class="bi bi-person-circle text-brand fs-4 flex-shrink-0"></i>
                                <div>
                                    <div class="fw-semibold mb-1">{{ $staff['name'] }}</div>
                                    <p class="text-muted small mb-0">
                                        <a href="tel:{{ $staff['phone'] }}" class="text-muted"><i class="bi bi-telephone me-1"></i>{{ $staff['phone'] }}</a><br>
                                        <a href="mailto:{{ $staff['email'] }}" class="text-muted"><i class="bi bi-envelope me-1"></i>{{ $staff['email'] }}</a>
                                    </p>
                                </div>
                            </div>
                        @endforeach
                        <a href="{{ route('contact.create') }}" class="btn btn-primary mt-2 align-self-start">
                            <i class="bi bi-send me-2"></i>Gửi liên hệ
                        </a>
                    </div>
                </div>
                <div class="col-lg-7" data-aos="fade-left">
                    <div class="rounded-4 overflow-hidden shadow-soft h-100" style="min-height: 360px;">
                        <iframe
                            src="https://www.google.com/maps?q={{ urlencode(config('site.contact.address')) }}&output=embed"
                            width="100%" height="100%" style="border:0; min-height: 360px;"
                            allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            title="Bản đồ chỉ đường đến {{ config('app.name') }}">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.Swiper) {
                new Swiper('.hero-swiper', {
                    loop: true,
                    autoHeight: true,
                    autoplay: { delay: 5000, disableOnInteraction: false },
                    pagination: { el: '.swiper-pagination', clickable: true },
                });
            }

            var counters = document.querySelectorAll('.js-counter');
            if (counters.length && 'IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) return;
                        var el = entry.target;
                        var target = parseInt(el.dataset.count, 10) || 0;
                        var duration = 1200;
                        var startTime = null;

                        function step(timestamp) {
                            if (!startTime) startTime = timestamp;
                            var progress = Math.min((timestamp - startTime) / duration, 1);
                            el.textContent = Math.floor(progress * target).toLocaleString('vi-VN');
                            if (progress < 1) requestAnimationFrame(step);
                            else el.textContent = target.toLocaleString('vi-VN');
                        }

                        requestAnimationFrame(step);
                        observer.unobserve(el);
                    });
                }, { threshold: 0.4 });
                counters.forEach(function (el) { observer.observe(el); });
            }
        });
    </script>
@endpush
