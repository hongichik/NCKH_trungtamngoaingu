@extends('layouts.app')

@section('title', $gallery->name)

@section('content')
    <div class="container py-5">
        <h1 class="h3 mb-1">{{ $gallery->name }}</h1>
        <p class="text-muted">{{ $gallery->description }}</p>

        <div class="row g-2 mt-2">
            @if ($gallery->image)
                <div class="col-md-4">
                    <img src="{{ Storage::url($gallery->image) }}" class="img-fluid rounded" alt="{{ $gallery->name }}">
                </div>
            @endif
            @foreach ($gallery->images as $image)
                <div class="col-md-4">
                    <img src="{{ Storage::url($image->image) }}" class="img-fluid rounded" alt="{{ $image->description }}">
                </div>
            @endforeach
        </div>
    </div>
@endsection
