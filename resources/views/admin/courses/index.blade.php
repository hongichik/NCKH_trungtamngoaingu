@extends('layouts.admin')

@section('title', 'Khoá học')

@section('content')
    <div class="d-flex flex-wrap gap-2 mb-3">
        <form class="d-flex gap-2" method="GET">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tìm khoá học">
            <select name="language" class="form-select w-auto" onchange="this.form.submit()"><option value="">Mọi ngôn ngữ</option><option value="en" @selected(request('language') === 'en')>Tiếng Anh</option><option value="zh" @selected(request('language') === 'zh')>Tiếng Trung</option></select>
        </form>
        @if (auth()->user()->hasPermission('learning', 'create'))
            <a href="{{ route('admin.courses.create') }}" class="btn btn-primary ms-auto"><i class="ti ti-plus me-1"></i>Tạo khoá học</a>
        @endif
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>Khoá học</th><th>Ngôn ngữ</th><th class="text-end">Bài học</th><th>Giáo viên được sửa</th><th class="text-end">Lớp</th><th></th></tr></thead>
                <tbody>
                    @forelse ($courses as $c)
                        <tr>
                            <td><a href="{{ route('admin.courses.edit', $c) }}" class="fw-semibold">{{ $c->title }}</a>
                                <div class="small text-muted">{{ $c->level }} @unless ($c->is_published)<span class="badge bg-secondary-lt">Ẩn</span>@endunless</div></td>
                            <td>{{ $c->language === 'zh' ? 'Tiếng Trung' : 'Tiếng Anh' }}</td>
                            <td class="text-end"><a href="{{ route('admin.courses.lessons.index', $c) }}">{{ $c->lessons_count }}</a></td>
                            <td class="small">{{ $c->editors->pluck('name')->implode(', ') ?: '—' }}</td>
                            <td class="text-end"><a href="{{ route('admin.classrooms.index', ['course_id' => $c->id]) }}">{{ $c->classrooms_count }}</a></td>
                            <td class="text-end"><div class="d-inline-flex gap-1">
                                <a href="{{ route('admin.classrooms.create', ['course_id' => $c->id]) }}" class="btn btn-sm btn-outline-primary">+ Lớp</a>
                                @if (auth()->user()->hasPermission('learning', 'delete'))
                                    <form method="POST" action="{{ route('admin.courses.destroy', $c) }}" onsubmit="return confirm(@js('Xoá khoá học “'.$c->title.'”? Toàn bộ bài học, bài tập của khoá sẽ bị xoá' . ($c->classrooms_count ? '; '.$c->classrooms_count.' lớp sẽ được giữ lại nhưng không còn gắn khoá học.' : '.')))">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Xoá khoá học"><i class="ti ti-trash"></i></button>
                                    </form>
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">Chưa có khoá học.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $courses->links() }}</div>
    </div>
@endsection
