@php $category = $post->categories->first(); @endphp
<div class="card h-100 post-card shadow-soft">
    <div class="position-relative">
        @if ($post->image)
            <img src="{{ Storage::url($post->image) }}" class="card-img-top" alt="{{ $post->title }}" loading="lazy">
        @else
            <div class="bg-secondary-subtle d-flex align-items-center justify-content-center" style="height: 190px; border-radius: .9rem .9rem 0 0;">
                <i class="bi bi-image text-secondary" style="font-size: 2.2rem;"></i>
            </div>
        @endif
        @if ($category)
            <span class="badge position-absolute top-0 start-0 m-2" style="background: var(--accent);">{{ $category->name }}</span>
        @endif
    </div>
    <div class="card-body">
        <h3 class="card-title fs-6"><a href="{{ route('public.show', $post->slug) }}" class="text-decoration-none">{{ $post->title }}</a></h3>
        <p class="card-text text-muted small mb-0">
            <i class="bi bi-calendar3 me-1"></i>{{ $post->created_at->format('d/m/Y') }}
        </p>
    </div>
</div>
