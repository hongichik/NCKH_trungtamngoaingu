@extends('layouts.app')

@section('title', $page->title)

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/ckeditor5/ckeditor5-content.css') }}">
    <style>
        /* Match the site font instead of CKEditor's default content font. */
        .ck-content { font-family: inherit; font-size: inherit; line-height: 1.7; }
        .ck-content .image { margin: 1rem auto; }
        .ck-content .media iframe, .ck-content iframe { max-width: 100%; }
        .ck-content table { width: 100%; }
    </style>
@endpush

@section('content')
    <div class="container py-5">
        <article>
            <h1 class="h3 mb-3">{{ $page->title }}</h1>

            @if ($page->image)
                <img src="{{ Storage::url($page->image) }}" class="img-fluid rounded mb-3" alt="{{ $page->title }}">
            @endif

            <div class="page-content ck-content">{!! $page->content !!}</div>
        </article>
    </div>
@endsection
