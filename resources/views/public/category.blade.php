@extends('layouts.app')

@section('title', $category->name)

@section('content')
    <div class="container py-5">
        <div class="text-center mb-5" data-aos="fade-up">
            <h1 class="h3 mb-2">{{ $category->name }}</h1>
            @if ($category->description)
                <p class="text-muted mb-0">{{ $category->description }}</p>
            @endif
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
                            <p class="text-muted text-center py-5 mb-0">Chưa có bài viết nào trong danh mục này.</p>
                        </div>
                    @endforelse
                </div>

                @if ($posts->hasPages())
                    <div class="mt-4">{{ $posts->links() }}</div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="sticky-lg-top d-flex flex-column gap-3" style="top: 90px;">
                    @include('public.partials.exam-cta')
                </div>
            </div>
        </div>
    </div>
@endsection
