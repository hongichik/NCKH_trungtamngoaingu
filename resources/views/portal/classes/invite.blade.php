@extends('layouts.portal')

@section('title', 'Lời mời vào lớp')

@section('content')
    <div class="card border-0 shadow-sm mx-auto" style="max-width: 520px">
        <div class="card-body text-center p-4">
            @if ($classroom)
                <i class="bi bi-people text-primary" style="font-size: 3rem"></i>
                <h1 class="h4 mt-2">{{ $classroom->name }}</h1>
                <p class="text-muted">{{ $classroom->course?->title }} · Giáo viên: {{ $classroom->teacher?->name }}</p>
                <form method="POST" action="{{ route('portal.classes.accept', $token) }}">@csrf
                    <button class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right"></i> Vào lớp</button>
                </form>
            @else
                <i class="bi bi-link-45deg text-muted" style="font-size: 3rem"></i>
                <h1 class="h5 mt-2">Link mời không còn hiệu lực</h1>
                <p class="text-muted mb-0">Hãy xin giáo viên link mời mới.</p>
            @endif
        </div>
    </div>
@endsection
