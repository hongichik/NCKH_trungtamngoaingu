@extends('layouts.admin')

@section('title', $course->exists ? $course->title : 'Tạo khoá học')

@section('content')
    @if ($course->exists)
        @include('admin.courses.partials.tabs', ['active' => 'info'])
    @endif
    <form method="POST" action="{{ $course->exists ? route('admin.courses.update', $course) : route('admin.courses.store') }}" enctype="multipart/form-data" class="row g-3">
        @csrf @if ($course->exists) @method('PUT') @endif
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body row g-3">
                    <div class="col-md-8"><label class="form-label required">Tên khoá học</label><input name="title" class="form-control" value="{{ old('title', $course->title) }}" required maxlength="200"></div>
                    <div class="col-md-4"><label class="form-label">Trình độ</label><input name="level" class="form-control" value="{{ old('level', $course->level) }}" placeholder="A2, B1, HSK 2…"></div>
                    <div class="col-md-4"><label class="form-label required">Ngôn ngữ</label>
                        <select name="language" class="form-select"><option value="en" @selected(old('language', $course->language) === 'en')>Tiếng Anh</option><option value="zh" @selected(old('language', $course->language) === 'zh')>Tiếng Trung</option></select></div>
                    <div class="col-12"><label class="form-label">Mô tả</label><textarea name="description" class="form-control" rows="4">{{ old('description', $course->description) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Ảnh bìa</label><input type="file" name="cover" class="form-control" accept="image/*">
                        @if ($course->cover_image)<img src="{{ asset('storage/'.$course->cover_image) }}" class="mt-2 rounded" style="max-height: 90px" alt="">@endif</div>
                    <div class="col-md-6 d-flex flex-column gap-2 justify-content-end">
                        <label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_published" value="1" @checked(old('is_published', $course->is_published))><span class="form-check-label">Đang hoạt động</span></label>
                        <label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="show_reading_preview" value="1" @checked(old('show_reading_preview', $course->show_reading_preview))><span class="form-check-label">Cho học viên xem trước bài đọc</span></label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Giáo viên được sửa khoá</h3></div>
                <div class="card-body" style="max-height: 360px; overflow: auto">
                    @php $selected = collect(old('editors', $course->editors->pluck('id')->all()))->map(fn ($v) => (int) $v)->all(); @endphp
                    @forelse ($teachers as $t)
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="editors[]" value="{{ $t->id }}" @checked(in_array($t->id, $selected, true))><span class="form-check-label">{{ $t->name }} <span class="text-muted small">{{ $t->email }}</span>@unless ($t->is_active)<span class="badge bg-secondary-lt ms-1">khoá</span>@endunless</span></label>
                    @empty
                        <div class="text-muted">Chưa có giáo viên. <a href="{{ route('admin.teachers.create') }}">Thêm giáo viên</a></div>
                    @endforelse
                </div>
                <div class="card-footer small text-muted">Được sửa: bài học, video, tài liệu, bài tập của khoá. Mặc định giáo viên không có quyền sửa.</div>
            </div>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">Lưu</button>
            <a href="{{ route('admin.courses.index') }}" class="btn btn-link">Huỷ</a>
        </div>
    </form>
    @if ($course->exists && auth()->user()->hasPermission('learning', 'delete'))
        <form method="POST" action="{{ route('admin.courses.destroy', $course) }}" class="mt-3 text-end" onsubmit="return confirm('Xoá khoá học này? Toàn bộ bài học, bài tập của khoá sẽ bị xoá; các lớp được giữ lại nhưng không còn gắn khoá học.')">@csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger">Xoá khoá học</button>
        </form>
    @endif
@endsection
