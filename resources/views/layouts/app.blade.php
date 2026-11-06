<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        $pageTitle = trim(($__env->yieldContent('title') ?: 'Trang chủ') . ' - ' . config('app.name'));
        $metaDescription = $__env->yieldContent('meta_description') ?: setting('meta_description', config('app.name'));
        $settingUrl = fn ($key, $fallback) => setting($key) ? Storage::url(setting($key)) : asset($fallback);
        $logoUrl = $settingUrl('logo', 'images/logo.jpg');
        $logoFooterUrl = $settingUrl('logo_footer', 'images/logo-mark.png');
        $faviconUrl = $settingUrl('favicon', 'images/favicon.png');
        $brandColor = setting('color_brand', '#0a4a75');
        $accentColor = setting('color_accent', '#ef5823');
        $parentOrg = setting('parent_org_name');
        $parentOrgUrl = setting('parent_org_url');
        $contactStaff = setting('contact_staff', config('site.contact.staff', []));
        $zalo = setting('social_zalo');
        $zaloUrl = $zalo && ! str_starts_with($zalo, 'http') ? 'https://zalo.me/'.preg_replace('/\D/', '', $zalo) : $zalo;
        $socials = array_filter([
            'facebook' => setting('social_facebook'),
            'youtube' => setting('social_youtube'),
            'zalo' => $zaloUrl,
        ]);
        $ogImage = $__env->yieldContent('og_image') ?: null;
    @endphp

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ Str::limit(strip_tags($metaDescription), 160, '') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="{{ $brandColor }}">

    <link rel="icon" type="image/png" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" href="{{ setting('favicon') ? $faviconUrl : asset('images/apple-touch-icon.png') }}">

    {{-- Open Graph / Twitter --}}
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ Str::limit(strip_tags($metaDescription), 200, '') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ Str::limit(strip_tags($metaDescription), 200, '') }}">
    @if ($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif

    @stack('structured_data')

    {{-- Performance: preconnect to third-party origins --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" rel="stylesheet">

    {{-- Organization / WebSite structured data (sitewide) --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'EducationalOrganization',
                    '@id' => url('/#organization'),
                    'name' => config('app.name'),
                    'url' => url('/'),
                    'logo' => $logoFooterUrl,
                ] + ($parentOrg ? ['parentOrganization' => array_filter([
                    '@type' => 'CollegeOrUniversity',
                    'name' => $parentOrg,
                    'url' => $parentOrgUrl,
                ])] : []),
                [
                    '@type' => 'WebSite',
                    '@id' => url('/#website'),
                    'name' => config('app.name'),
                    'url' => url('/'),
                    'inLanguage' => 'vi-VN',
                    'publisher' => ['@id' => url('/#organization')],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>

    <style>
        /* Hiệu ứng trượt vào (AOS) đẩy khối ra ngoài màn hình trước khi chạy → không cho tạo thanh cuộn ngang */
        html, body { overflow-x: clip; }
        :root {
            /* Sampled from the center's official emblem (navy ring + orange accent) */
            /* Editable in Admin > Cài đặt website; darker shades are derived automatically. */
            --brand: {{ $brandColor }};
            --brand-dark: color-mix(in srgb, {{ $brandColor }} 78%, #000);
            --accent: {{ $accentColor }};
            --accent-dark: color-mix(in srgb, {{ $accentColor }} 85%, #000);
            --ink: #101828;
        }
        body {
            font-family: 'Be Vietnam Pro', -apple-system, "Segoe UI", Roboto, sans-serif;
            color: var(--ink);
            background: #f7f8fb;
        }
        h1, h2, h3, h4, h5, h6 { font-weight: 700; }
        a { transition: color .15s ease; }

        .hero-section { overflow: hidden; }
        .hero-slide {
            position: relative;
            display: flex;
            align-items: flex-end;
            min-height: 480px;
            background-size: cover;
            background-position: center;
            background-color: var(--brand);
        }
        .hero-slide-bg, .hero-slide-bg img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .hero-slide-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0) 30%, rgba(0, 0, 0, .72) 100%);
        }
        .hero-slide-content { position: relative; z-index: 2; padding-bottom: 3.5rem; max-width: 720px; }
        /* Banner template: full-width posters (design already contains all text/branding). Desktop caps height at 480px (cropped to fill); mobile shows the full image at its natural ratio. */
        .hero-slide-poster-img {
            display: block;
            width: 100%;
            height: auto;
            max-height: 480px;
            object-fit: cover;
            object-position: center;
        }
        .hero-swiper .swiper-pagination-bullet { background: #fff; opacity: .6; }
        .hero-swiper .swiper-pagination-bullet-active { opacity: 1; background: var(--accent); }
        @media (max-width: 767.98px) {
            .hero-slide { min-height: 420px; }
            .hero-slide-content { padding-bottom: 2rem; }
            .hero-slide-poster-img { max-height: none; }
        }

        .navbar { box-shadow: 0 1px 2px rgba(16, 24, 40, .06); }
        .navbar-brand img { height: 44px; width: auto; }
        .nav-link { font-weight: 500; }
        .navbar .nav-link.active { color: var(--brand) !important; }
        .navbar .dropdown-item.active, .navbar .dropdown-item:active { background: var(--brand); color: #fff; }

        .btn-primary { background: var(--accent); border-color: var(--accent); }
        .btn-primary:hover { background: var(--accent-dark); border-color: var(--accent-dark); }
        .btn-outline-primary { color: var(--brand); border-color: var(--brand); }
        .btn-outline-primary:hover { background: var(--brand); border-color: var(--brand); color: #fff; }
        .text-brand { color: var(--brand); }
        .bg-brand { background: var(--brand); }
        .text-accent { color: var(--accent); }
        .badge.text-bg-primary { background: var(--accent) !important; }

        .card { border: 1px solid rgba(16, 24, 40, .08); border-radius: .9rem; }
        .post-card img, .gallery-card img { height: 190px; object-fit: cover; border-radius: .9rem .9rem 0 0; }
        .post-card .card-title a { color: var(--ink); }
        .post-card .card-title a:hover { color: var(--brand); }
        .shadow-soft { box-shadow: 0 8px 30px rgba(16, 24, 40, .08); }

        /* Rich-text content (bài viết/trang tĩnh) may contain raw <img> tags with no size
           constraints from the WYSIWYG editor; cap them to the content width without
           upscaling images that already fit. */
        .post-content img, .page-content img {
            max-width: 100%;
            height: auto;
        }

        footer.site-footer { background: #0a1f33; color: #b9c2d8; }
        footer.site-footer a { color: #dbe2f3; text-decoration: none; }
        footer.site-footer a:hover { color: #fff; }
        footer.site-footer h6 { color: #fff; font-weight: 700; }
        footer.site-footer img { height: 40px; width: auto; }
        .footer-social {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 2.2rem; height: 2.2rem; padding: 0 .5rem; border-radius: 50%;
            background: rgba(255, 255, 255, .1); color: #fff !important;
        }
        .footer-social:hover { background: var(--accent); }
        .footer-logo-badge {
            background: #fff;
            border-radius: .6rem;
            padding: .5rem .75rem;
            line-height: 0;
        }

        .skip-link {
            position: absolute; left: -9999px; top: 0; z-index: 2000;
            background: #fff; color: var(--ink); padding: .6rem 1rem; border-radius: .5rem;
        }
        .skip-link:focus { left: 1rem; top: 1rem; }

        @media (max-width: 991.98px) {
            .navbar-brand img { height: 36px; }
        }

        .news-tabs-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .news-tabs-grid button.nav-link {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: .82rem;
            font-weight: 600;
            line-height: 1.25;
            padding: .65rem .5rem;
            min-height: 3.1rem;
            border-radius: .6rem;
            background: #fff;
            color: var(--brand);
            border: 1px solid rgba(10, 74, 117, .18);
        }
        .news-tabs-grid button.nav-link.active {
            background: var(--brand);
            color: #fff;
            border-color: var(--brand);
        }
    </style>

    @stack('head')
</head>
<body>
    <a href="#main-content" class="skip-link">Bỏ qua và đến nội dung chính</a>

    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}{{ $parentOrg ? ' - trực thuộc '.$parentOrg : '' }}" @unless (setting('logo')) width="611" height="97" @endunless>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Mở menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav ms-auto">
                    @foreach ($siteMenus['header'] ?? [] as $menuItem)
                        @if ($menuItem->children->isNotEmpty())
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle {{ $menuItem->isActive() ? 'active' : '' }}" href="{{ $menuItem->url }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">{{ $menuItem->title }}</a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    @foreach ($menuItem->children as $child)
                                        <li><a class="dropdown-item {{ $child->isActive() ? 'active' : '' }}" href="{{ $child->url }}" @if ($child->open_new_tab) target="_blank" rel="noopener" @endif>{{ $child->title }}</a></li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li class="nav-item"><a class="nav-link {{ $menuItem->isActive() ? 'active' : '' }}" href="{{ $menuItem->url }}" @if ($menuItem->open_new_tab) target="_blank" rel="noopener" @endif>{{ $menuItem->title }}</a></li>
                        @endif
                    @endforeach
                    {{-- Lối vào cho học viên / thí sinh đã có tài khoản (các mục trên là menu quản trị tự cấu hình) --}}
                    <li class="nav-item ms-lg-2 d-flex align-items-center py-2 py-lg-0">
                        @if (auth('student')->check())
                            <a href="{{ route('portal.home') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-circle me-1"></i>{{ Str::limit(auth('student')->user()->full_name, 18) }}</a>
                        @else
                            <a href="{{ route('portal.auth') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập</a>
                        @endif
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main id="main-content">
        @if (session('status'))
            <div class="container pt-4">
                <div class="alert alert-success">{{ session('status') }}</div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="site-footer py-5 mt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <span class="footer-logo-badge d-inline-block mb-3">
                        <img src="{{ $logoFooterUrl }}" alt="{{ config('app.name') }}" loading="lazy">
                    </span>
                    @if (setting('site_intro'))
                        <p class="mb-1 small">{{ setting('site_intro') }}</p>
                    @endif
                    @if ($parentOrg)
                        <p class="mb-0 small">Trực thuộc @if ($parentOrgUrl)<a href="{{ $parentOrgUrl }}" target="_blank" rel="noopener">{{ $parentOrg }}</a>@else{{ $parentOrg }}@endif.</p>
                    @endif
                    @if ($socials)
                        <div class="d-flex gap-2 mt-3">
                            @foreach ($socials as $network => $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="footer-social" aria-label="{{ ucfirst($network) }}">
                                    @if ($network === 'zalo')<span class="fw-bold small">Zalo</span>@else<i class="bi bi-{{ $network }}"></i>@endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="col-6 col-lg-2">
                    <h6>Khám phá</h6>
                    <ul class="list-unstyled small">
                        @foreach ($siteMenus['footer_explore'] ?? [] as $menuItem)
                            <li class="mb-2"><a href="{{ $menuItem->url }}" @if ($menuItem->open_new_tab) target="_blank" rel="noopener" @endif>{{ $menuItem->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h6>Hỗ trợ</h6>
                    <ul class="list-unstyled small">
                        @foreach ($siteMenus['footer_support'] ?? [] as $menuItem)
                            <li class="mb-2"><a href="{{ $menuItem->url }}" @if ($menuItem->open_new_tab) target="_blank" rel="noopener" @endif>{{ $menuItem->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h6>Liên hệ</h6>
                    <p class="mb-2 small"><i class="bi bi-geo-alt me-1"></i>{{ setting('contact_address', config('site.contact.address')) }}</p>
                    @if (setting('contact_email'))
                        <p class="mb-2 small"><i class="bi bi-envelope me-1"></i><a href="mailto:{{ setting('contact_email') }}">{{ setting('contact_email') }}</a></p>
                    @endif
                    @foreach ($contactStaff as $staff)
                        <p class="mb-2 small">{{ $staff['name'] }}: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $staff['phone']) }}">{{ $staff['phone'] }}</a></p>
                    @endforeach
                    <a href="{{ route('contact.create') }}" class="btn btn-sm btn-primary mt-1">Liên hệ tư vấn</a>
                </div>
            </div>
            <hr class="border-secondary my-4">
            <p class="mb-0 small text-center text-md-start">&copy; {{ date('Y') }} {{ config('app.name') }}. Bảo lưu mọi quyền.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.AOS) {
                AOS.init({ duration: 600, once: true, offset: 80 });
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
