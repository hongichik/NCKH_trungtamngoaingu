@extends('layouts.app')

@section('title', 'Tin tức')

@section('content')
    <div class="container py-5">
        <div class="text-center mb-5" data-aos="fade-up">
            <h1 class="h3 mb-2">Tin tức</h1>
            <p class="text-muted mb-0">Cập nhật thông báo tuyển sinh, lịch thi và hoạt động của trung tâm</p>
        </div>

        <div class="row g-4 g-lg-5">
            <div class="col-lg-8">
                <div class="row g-4">
                    @forelse ($posts as $post)
                        <div class="col-md-6" data-aos="fade-up">
                            @include('public.partials.post-card', ['post' => $post])
                        </div>
                    @empty
                        <div class="col-12">
                            <p class="text-muted text-center py-5 mb-0">Chưa có bài viết nào.</p>
                        </div>
                    @endforelse
                </div>

                @if ($posts->hasPages())
                    <div class="mt-4">{{ $posts->links() }}</div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="sticky-lg-top" style="top: 90px;">
                    @if ($categories->isNotEmpty())
                        <div class="card shadow-soft border-0 mb-4">
                            <div class="card-body p-4">
                                <h2 class="h6 mb-3">Danh mục</h2>
                                <ul class="list-unstyled mb-0">
                                    @foreach ($categories as $category)
                                        <li class="{{ !$loop->last ? 'mb-2 pb-2 border-bottom' : '' }}">
                                            <a href="{{ route('category.show', $category->slug) }}" class="d-flex justify-content-between align-items-center text-decoration-none text-body">
                                                <span><i class="bi bi-folder2 text-brand me-2"></i>{{ $category->name }}</span>
                                                <span class="badge bg-light text-muted">{{ $category->posts_count }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <div class="d-flex flex-column gap-3">
                        @include('public.partials.exam-cta')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
