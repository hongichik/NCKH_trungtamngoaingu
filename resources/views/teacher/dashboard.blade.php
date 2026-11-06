@extends('layouts.teacher')

@section('title', 'Xin chào, '.auth('teacher')->user()->name)

@section('actions')
    <div class="d-flex gap-2">
        <a href="{{ route('teacher.classrooms.index') }}" class="btn btn-primary"><i class="ti ti-users me-1"></i>Lớp học của tôi</a>
        @if ($courses->isNotEmpty())<a href="{{ route('teacher.courses.index') }}" class="btn btn-outline-primary"><i class="ti ti-school me-1"></i>Khoá học tôi được sửa</a>@endif
    </div>
@endsection

@section('content')
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body">
            <div class="text-muted small">Lớp học</div><div class="h2 mb-0">{{ $classCount }}</div>
        </div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body">
            <div class="text-muted small">Lượt AI hôm nay</div>
            <div class="h2 mb-0">{{ $aiToday }}@if ($aiLimit)<span class="text-muted fs-5"> / {{ $aiLimit }}</span>@endif</div>
        </div></div></div>
    </div>

    @if ($courses->isNotEmpty())
        <div class="card">
            <div class="card-header"><h3 class="card-title">Khoá học tôi được sửa</h3></div>
            <div class="list-group list-group-flush">
                @foreach ($courses as $c)
                    <a href="{{ route('teacher.lessons.index', $c) }}" class="list-group-item list-group-item-action d-flex align-items-center">
                        <span class="avatar me-3 {{ $c->language === 'zh' ? 'bg-red-lt' : 'bg-blue-lt' }}">{{ $c->language === 'zh' ? '中' : 'EN' }}</span>
                        <div class="flex-fill"><div class="fw-semibold">{{ $c->title }}</div><div class="small text-muted">{{ $c->lessons_count }} bài học</div></div>
                        <i class="ti ti-chevron-right text-muted"></i>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
@endsection
