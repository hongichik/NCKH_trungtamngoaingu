@extends($layout)

@section('title', 'Bài học — '.$course->title)

@section('content')
    @if ($prefix === 'admin.courses.lessons')
        @include('admin.courses.partials.tabs', ['course' => $course, 'active' => 'lessons'])
    @endif
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                @php $noVocab = $lessons->filter(fn ($l) => empty($l->vocabulary))->count(); @endphp
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h3 class="card-title me-auto">Bài học ({{ $lessons->count() }})</h3>
                    @if ($noVocab)
                        <form method="POST" action="{{ route($prefix.'.vocabulary.ai-all', $course) }}" onsubmit="return confirm('AI lập danh sách từ vựng cho {{ $noVocab }} bài học chưa có? (chạy nền, vài phút)')">@csrf
                            <button class="btn btn-sm btn-outline-purple"><i class="ti ti-cards me-1"></i>AI lập từ vựng cho {{ $noVocab }} bài chưa có</button></form>
                    @endif
                </div>
                <form method="POST" action="{{ route($prefix.'.reorder', $course) }}" id="lesson-order">@csrf
                    <ul class="list-group list-group-flush">
                        @forelse ($lessons as $l)
                            <li class="list-group-item d-flex align-items-center gap-2" data-id="{{ $l->id }}">
                                <input type="hidden" name="order[]" value="{{ $l->id }}">
                                <span class="text-muted lesson-no" style="width: 2rem">{{ $loop->iteration }}.</span>
                                <div class="flex-fill">
                                    <a href="{{ route($prefix.'.edit', [$course, $l]) }}" class="fw-semibold">{{ $l->title }}</a>
                                    <div class="small text-muted">
                                        <b class="{{ $l->exercises_count ? 'text-success' : 'text-danger' }}">{{ $l->exercises_count }} bài tập</b> · {{ $l->videos_count }} video · {{ $l->materials_count }} tài liệu · <span class="{{ empty($l->vocabulary) ? 'text-muted' : '' }}">{{ count($l->vocabulary ?? []) }} từ vựng</span>
                                        @if ($l->require_video) · <span class="text-warning">phải xem xong video</span> @endif
                                        @unless ($l->is_published) · <span class="badge bg-secondary-lt">Ẩn</span> @endunless
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-ghost-secondary" data-move="-1" title="Lên">▲</button>
                                <button type="button" class="btn btn-sm btn-ghost-secondary" data-move="1" title="Xuống">▼</button>
                                <button type="button" class="btn btn-sm btn-ghost-danger" title="Xoá bài học"
                                        data-delete-lesson="{{ route($prefix.'.destroy', [$course, $l]) }}" data-title="{{ $l->title }}"
                                        data-exercises="{{ $l->all_exercises_count }}" data-videos="{{ $l->videos_count }}" data-materials="{{ $l->materials_count }}" data-attempts="{{ $attempts[$l->id] ?? 0 }}"><i class="ti ti-trash"></i></button>
                            </li>
                        @empty
                            <li class="list-group-item text-muted py-4 text-center">Chưa có bài học.</li>
                        @endforelse
                    </ul>
                    @if ($lessons->count() > 1)<div class="card-footer text-end"><button class="btn btn-sm btn-primary">Lưu thứ tự</button></div>@endif
                </form>
            </div>
        </div>
        <div class="col-lg-4">
            <form method="POST" action="{{ route($prefix.'.store', $course) }}" class="card">@csrf
                <div class="card-header"><h3 class="card-title">Thêm bài học</h3></div>
                <div class="card-body">
                    <div class="mb-2"><label class="form-label required">Tên bài</label><input name="title" class="form-control" required maxlength="200"></div>
                    <div><label class="form-label">Tóm tắt</label><textarea name="summary" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary">Tạo bài học</button></div>
            </form>
            <div class="small text-muted mt-2">Mỗi bài học: tải file bài tập (Word / PDF / ảnh, kèm file nghe) để AI dựng bài tập, hoặc để AI tự tạo bài tập, soạn tay; thêm video và tài liệu.</div>
        </div>
    </div>
    @include('lessons.editor.partials.delete-modal')
@endsection

@push('scripts')
<script>
document.querySelectorAll('#lesson-order [data-move]').forEach((b) => b.addEventListener('click', () => {
    const li = b.closest('li');
    if (b.dataset.move === '-1' && li.previousElementSibling) li.parentNode.insertBefore(li, li.previousElementSibling);
    if (b.dataset.move === '1' && li.nextElementSibling) li.parentNode.insertBefore(li.nextElementSibling, li);
    document.querySelectorAll('#lesson-order .lesson-no').forEach((n, i) => { n.textContent = (i + 1) + '.'; });
}));
</script>
@endpush
