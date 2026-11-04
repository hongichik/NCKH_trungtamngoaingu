@extends('layouts.app')

@section('title', 'Thư viện ảnh')

@section('content')
    <div class="container py-5">
        <h1 class="h3 mb-4">Thư viện ảnh</h1>

        <div class="row g-4">
            @foreach ($galleries as $gallery)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 gallery-card">
                        @if ($gallery->image)
                            <img src="{{ Storage::url($gallery->image) }}" class="card-img-top" alt="{{ $gallery->name }}">
                        @endif
                        <div class="card-body">
                            <h5 class="card-title fs-6"><a href="{{ route('galleries.show', $gallery->slug) }}" class="text-decoration-none">{{ $gallery->name }}</a></h5>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $galleries->links() }}</div>
    </div>
@endsection
