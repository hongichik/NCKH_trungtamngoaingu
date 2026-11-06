@extends('layouts.teacher')

@section('title', 'Khoá học tôi được sửa')

@section('content')
    <div class="row row-cards">
        @forelse ($courses as $c)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    @if ($c->cover_image)<img src="{{ asset('storage/'.$c->cover_image) }}" class="card-img-top" style="max-height: 140px; object-fit: cover" alt="">@endif
                    <div class="card-body">
                        <div class="text-muted small">{{ $c->language === 'zh' ? 'Tiếng Trung' : 'Tiếng Anh' }}{{ $c->level ? ' · '.$c->level : '' }} · {{ $c->lessons_count }} bài học · {{ $c->classrooms_count }} lớp</div>
                        <h3 class="card-title mb-2">{{ $c->title }}</h3>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('teacher.lessons.index', $c) }}" class="btn btn-sm btn-primary"><i class="ti ti-list-numbers me-1"></i>Bài học & bài tập</a>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="alert alert-info">Bạn chưa được cấp quyền sửa khoá học nào.</div></div>
        @endforelse
    </div>
@endsection
