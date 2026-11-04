@extends('layouts.app')

@section('title', $page->title)

@section('content')
    <div class="container py-5">
        <article>
            <h1 class="h3 mb-3">{{ $page->title }}</h1>

            @if ($page->image)
                <img src="{{ Storage::url($page->image) }}" class="img-fluid rounded mb-3" alt="{{ $page->title }}">
            @endif

            <div class="page-content">{!! $page->content !!}</div>
        </article>
    </div>
@endsection
