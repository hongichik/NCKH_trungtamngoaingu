@extends('layouts.app')

@section('title', $post->title)

@section('content')
    <div class="container py-5">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-8">
                <article>
                    @if ($post->categories->isNotEmpty())
                        <div class="mb-3">
                            @foreach ($post->categories as $category)
                                <a href="{{ route('category.show', $category->slug) }}" class="badge text-decoration-none mb-1" style="background: var(--accent);">{{ $category->name }}</a>
                            @endforeach
                        </div>
                    @endif

                    <h1 class="h3 mb-2">{{ $post->title }}</h1>
                    <p class="text-muted small mb-4">
                        <i class="bi bi-calendar3 me-1"></i>{{ $post->created_at->format('d/m/Y') }}
                        <span class="mx-2">&middot;</span>
                        <i class="bi bi-eye me-1"></i>{{ $post->views }} lượt xem
                    </p>

                    @if ($post->image)
                        <img src="{{ Storage::url($post->image) }}" class="img-fluid rounded-4 shadow-soft mb-4" alt="{{ $post->title }}">
                    @endif

                    <div class="post-content">{!! $post->content !!}</div>

                    @if ($post->images->isNotEmpty())
                        <div class="row g-2 mt-4">
                            @foreach ($post->images as $image)
                                <div class="col-md-3">
                                    <img src="{{ Storage::url($image->image) }}" class="img-fluid rounded" alt="{{ $image->description }}">
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            </div>

            <div class="col-lg-4">
                <div class="sticky-lg-top d-flex flex-column gap-4" style="top: 90px;">
                    @include('public.partials.exam-cta')

                    {{-- Related posts --}}
                    @if ($relatedPosts->isNotEmpty())
                        <div class="card shadow-soft border-0">
                            <div class="card-body p-4">
                                <h2 class="h6 mb-3">Bài viết liên quan</h2>
                                <ul class="list-unstyled mb-0">
                                    @foreach ($relatedPosts as $related)
                                        <li class="d-flex gap-3 {{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                                            <a href="{{ route('public.show', $related->slug) }}" class="flex-shrink-0">
                                                @if ($related->image)
                                                    <img src="{{ Storage::url($related->image) }}" alt="{{ $related->title }}" class="rounded-3" style="width: 72px; height: 56px; object-fit: cover;">
                                                @else
                                                    <div class="rounded-3 bg-secondary-subtle d-flex align-items-center justify-content-center" style="width: 72px; height: 56px;">
                                                        <i class="bi bi-image text-secondary"></i>
                                                    </div>
                                                @endif
                                            </a>
                                            <div>
                                                <a href="{{ route('public.show', $related->slug) }}" class="text-decoration-none text-body">
                                                    <div class="small fw-semibold lh-sm mb-1">{{ Str::limit($related->title, 60) }}</div>
                                                </a>
                                                <div class="text-muted" style="font-size: .78rem;">
                                                    <i class="bi bi-calendar3 me-1"></i>{{ $related->created_at->format('d/m/Y') }}
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    {{-- Contact info --}}
                    <div class="card shadow-soft border-0">
                        <div class="card-body p-4">
                            <h2 class="h6 mb-3">Liên hệ tư vấn</h2>
                            <div class="d-flex gap-3 mb-3">
                                <i class="bi bi-geo-alt-fill text-accent fs-5 flex-shrink-0"></i>
                                <p class="text-muted small mb-0">{{ config('site.contact.address') }}</p>
                            </div>
                            @foreach (config('site.contact.staff') as $staff)
                                <div class="d-flex gap-3 mb-3">
                                    <i class="bi bi-person-circle text-brand fs-5 flex-shrink-0"></i>
                                    <div>
                                        <div class="fw-semibold small mb-1">{{ $staff['name'] }}</div>
                                        <p class="text-muted small mb-0">
                                            <a href="tel:{{ $staff['phone'] }}" class="text-muted d-block"><i class="bi bi-telephone me-1"></i>{{ $staff['phone'] }}</a>
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                            <a href="{{ route('contact.create') }}" class="btn btn-primary w-100 mt-2">
                                <i class="bi bi-send me-2"></i>Gửi liên hệ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
