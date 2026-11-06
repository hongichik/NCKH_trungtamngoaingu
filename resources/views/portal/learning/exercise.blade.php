@extends('layouts.portal')

@section('title', $set->title)

@section('content')
    @php $cls = null; @endphp
    <nav class="small mb-2"><a href="{{ route('portal.classes.index') }}">Lớp học của tôi</a>@if ($cls) / <a href="{{ route('portal.classes.show', $cls) }}">{{ $cls->name }}</a>@endif </nav>
    <h1 class="h4 mb-3">{{ $set->title }}</h1>

    <form method="POST" action="{{ route('portal.learning.submit', [$course, $set]) }}" id="exercise-form">
        @csrf
        @include('portal.learning.partials.section', ['prefix' => 'answers'])
        <button class="btn btn-success btn-lg w-100" onclick="return confirm('Nộp bài? Đã nộp thì không sửa được.')"><i class="bi bi-send"></i> Nộp bài</button>
        @if ($set->gradedByAi())<div class="small text-muted text-center mt-2">Bài {{ $set->skill === 'speaking' ? 'Nói' : 'Viết' }} được AI chấm sau khi nộp (khoảng 10–60 giây).</div>@endif
    </form>
@endsection

@push('scripts')
    @include('portal.learning.partials.exercise-scripts')
@endpush
