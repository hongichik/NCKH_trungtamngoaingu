@extends('layouts.app')

@section('title', 'Trang chủ')
@section('meta_description', setting('meta_description', config('app.name')))
@section('og_image', optional($banners->firstWhere('image'))->image_url ?? (optional($heroPosts->first())->image ? Storage::url($heroPosts->first()->image) : asset('images/logo.jpg')))

@section('content')
    {{-- Hero banners: managed in Admin > Giao diện website > Banner trang chủ --}}
    @if ($banners->isNotEmpty())
        <section class="hero-section">
            <div class="swiper hero-swiper">
                <div class="swiper-wrapper">
                    @foreach ($banners as $banner)
                        @php
                            $target = $banner->open_new_tab ? 'target="_blank" rel="noopener"' : '';
                            $gradient = [
                                'brand' => 'linear-gradient(135deg, var(--brand), var(--brand-dark))',
                                'accent' => 'linear-gradient(135deg, var(--accent), var(--brand))',
                                'dark' => 'linear-gradient(135deg, #1f2937, #0b1220)',
                            ][$banner->background] ?? 'linear-gradient(135deg, var(--brand), var(--brand-dark))';
                        @endphp
                        <div class="swiper-slide">
                            @if ($banner->type === 'image')
                                {{-- Poster: always full width, height follows the image ratio --}}
                                @if ($banner->button_href)<a href="{{ $banner->button_href }}" {!! $target !!}>@endif
                                    <picture>
                                        @if ($banner->image_mobile_url)
                                            <source media="(max-width: 767.98px)" srcset="{{ $banner->image_mobile_url }}">
                                        @endif
                                        <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" class="hero-slide-poster-img" @if (! $loop->first) loading="lazy" @endif>
                                    </picture>
                                @if ($banner->button_href)</a>@endif
                            @else
                                <div class="hero-slide" style="background: {{ $gradient }};">
                                    @if ($banner->image_url)
                                        <picture class="hero-slide-bg">
                                            @if ($banner->image_mobile_url)
                                                <source media="(max-width: 767.98px)" srcset="{{ $banner->image_mobile_url }}">
                                            @endif
                                            <img src="{{ $banner->image_url }}" alt="" @if (! $loop->first) loading="lazy" @endif>
                                        </picture>
                                    @endif
                                    <div class="hero-slide-overlay"></div>
                                    <div class="container position-relative">
                                        <div class="hero-slide-content text-white" @if ($loop->first) data-aos="fade-up" @endif>
                                            @if ($banner->badge)
                                                <span class="badge bg-white {{ $banner->background === 'accent' ? 'text-accent' : 'text-brand' }} mb-3 fw-semibold">{{ $banner->badge }}</span>
                                            @endif
                                            @if ($loop->first)
                                                <h1 class="display-5 fw-bold text-white mb-3">{{ $banner->title }}</h1>
                                            @else
                                                <h2 class="display-6 fw-bold text-white mb-3">{{ $banner->title }}</h2>
                                            @endif
                                            @if ($banner->description)
                                                <p class="lead mb-4" style="max-width: 620px; opacity: .92;">{{ $banner->description }}</p>
                                            @endif
                                            @if ($banner->bullet_list)
                                                <ul class="list-unstyled mb-4">
                                                    @foreach ($banner->bullet_list as $point)
                                                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i>{{ $point }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                            @if ($banner->button_text && $banner->button_href)
                                                <a href="{{ $banner->button_href }}" class="btn btn-light btn-lg fw-semibold text-brand" {!! $target !!}>
                                                    @if (str_starts_with($banner->button_href, 'tel:'))<i class="bi bi-telephone me-2"></i>@endif{{ $banner->button_text }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($banners->count() > 1)
                    <div class="swiper-pagination"></div>
                @endif
            </div>
        </section>
    @endif

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
    @php $fields = setting('home_fields', []); @endphp
    @if ($fields)
    <section class="py-5 bg-white">
        <div class="container">
            <div class="mb-4 text-center" data-aos="fade-up">
                <h2 class="h3 mb-1">{{ setting('home_fields_title', 'Lĩnh vực đào tạo') }}</h2>
                @if (setting('home_fields_subtitle'))<p class="text-muted mb-0">{{ setting('home_fields_subtitle') }}</p>@endif
            </div>
            <div class="row g-4">
                @foreach ($fields as $i => $field)
                    <div class="col-md-6 col-lg-{{ count($fields) % 3 === 0 || count($fields) > 4 ? 4 : (count($fields) === 4 ? 3 : 12 / max(1, count($fields))) }}" data-aos="fade-up" data-aos-delay="{{ ($i % 3) * 100 }}">
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
    @endif

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
    @php $reasons = setting('home_reasons', []); @endphp
    @if ($reasons)
    <section class="py-5 bg-white">
        <div class="container">
            <div class="mb-4 text-center" data-aos="fade-up">
                <h2 class="h3 mb-1">{{ setting('home_reasons_title', 'Vì sao chọn '.config('app.name')) }}</h2>
                @if (setting('home_reasons_subtitle'))<p class="text-muted mb-0">{{ setting('home_reasons_subtitle') }}</p>@endif
            </div>
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
    @endif

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
                                <p class="text-muted small mb-0">{{ setting('contact_address', config('site.contact.address')) }}</p>
                            </div>
                        </div>
                        @foreach (setting('contact_staff', config('site.contact.staff', [])) as $staff)
                            <div class="d-flex gap-3 mb-4">
                                <i class="bi bi-person-circle text-brand fs-4 flex-shrink-0"></i>
                                <div>
                                    <div class="fw-semibold mb-1">{{ $staff['name'] }}</div>
                                    <p class="text-muted small mb-0">
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $staff['phone']) }}" class="text-muted"><i class="bi bi-telephone me-1"></i>{{ $staff['phone'] }}</a>
                                        @if (! empty($staff['email']))<br><a href="mailto:{{ $staff['email'] }}" class="text-muted"><i class="bi bi-envelope me-1"></i>{{ $staff['email'] }}</a>@endif
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
                            src="https://www.google.com/maps?q={{ urlencode(setting('map_query', setting('contact_address', config('site.contact.address')))) }}&output=embed"
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
            if (window.Swiper && document.querySelector('.hero-swiper')) {
                var slideCount = document.querySelectorAll('.hero-swiper .swiper-slide').length;
                new Swiper('.hero-swiper', {
                    loop: slideCount > 1,
                    allowTouchMove: slideCount > 1,
                    autoHeight: true,
                    autoplay: slideCount > 1 ? { delay: 5000, disableOnInteraction: false } : false,
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
