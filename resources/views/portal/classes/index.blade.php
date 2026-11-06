@extends('layouts.portal')

@section('title', 'Lớp học của tôi')

@section('content')
    <h1 class="h4 mb-3">Lớp học của tôi</h1>
    <div class="row g-3">
        @forelse ($classes as $c)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('portal.classes.show', $c) }}" class="card h-100 border-0 shadow-sm text-decoration-none">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-1">
                            <span class="badge {{ $c->course?->language === 'zh' ? 'text-bg-danger' : 'text-bg-primary' }}">{{ $c->course?->language === 'zh' ? 'Tiếng Trung' : 'Tiếng Anh' }}</span>
                            @if ($c->status() !== 'active')<span class="badge text-bg-secondary ms-1">{{ \App\Models\Classroom::STATUSES[$c->status()] }}</span>@endif
                            @if ($c->todo && $c->accessOpen())<span class="badge text-bg-warning ms-auto">{{ $c->todo }} bài cần làm</span>@endif
                        </div>
                        <div class="fw-semibold fs-5 text-dark">{{ $c->name }}</div>
                        <div class="small text-muted">{{ $c->course?->title }} · Giáo viên: {{ $c->teacher?->name }}</div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12"><div class="alert alert-info mb-0">Bạn chưa thuộc lớp học nào. Trung tâm sẽ thêm tài khoản của bạn vào lớp.</div></div>
        @endforelse
    </div>
@endsection
