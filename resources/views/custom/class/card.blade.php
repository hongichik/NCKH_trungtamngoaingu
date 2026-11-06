{{--
    Bài tập theo bài học của lớp (trang lớp — quản trị / giáo viên).
    Biến: $classroom, $prefix (admin.classrooms | teacher.classrooms), $canManage (giáo viên có quyền tuỳ chỉnh bài tập ở lớp), $libraryUrl (không dùng nữa)
--}}
@php
    $isAdmin = str_starts_with($prefix, 'admin.');
    $lessons = $classroom->course?->lessons()->with(['exercises' => fn ($q) => $q->whereIn('status', ['published', 'draft'])->where(fn ($w) => $w->whereNull('source_classroom_id')->orWhere('source_classroom_id', $classroom->id))])->get() ?? collect();
    $submitted = \App\Models\ClassroomCustomExercise::where('classroom_id', $classroom->id)
        ->withCount(['attempts as n' => fn ($q) => $q->where('status', '!=', 'in_progress')])->pluck('n', 'custom_exercise_id');
    $canEditCourse = $isAdmin || auth('teacher')->user()?->canEditCourse($classroom->course);
@endphp
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">Bài học & bài tập của lớp</h3>
        @if ($canEditCourse && $classroom->course)<div class="card-actions"><a href="{{ route($isAdmin ? 'admin.courses.lessons.index' : 'teacher.lessons.index', $classroom->course) }}" class="btn btn-sm btn-outline-primary">Soạn bài học của khoá</a></div>@endif
    </div>
    <div class="list-group list-group-flush">
        @forelse ($lessons as $l)
            <div class="list-group-item">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold flex-fill">{{ $loop->iteration }}. {{ $l->title }}</span>
                    @if (! $isAdmin && $canManage)
                        <form method="POST" action="{{ route('teacher.custom.store', $classroom->course_id) }}">@csrf
                            <input type="hidden" name="lesson_id" value="{{ $l->id }}"><input type="hidden" name="source_classroom_id" value="{{ $classroom->id }}">
                            <input type="hidden" name="title" value="Bài tập riêng — {{ $l->title }}"><input type="hidden" name="skill" value="mixed">
                            <button class="btn btn-sm btn-ghost-primary" title="Bài tập chỉ lớp này thấy">+ Bài tập riêng cho lớp</button>
                        </form>
                        <a href="{{ route('teacher.ai-import.create', [$classroom->course_id, 'lesson' => $l->id]) }}" class="btn btn-sm btn-ghost-purple" title="Tải file bài tập cho AI dựng (bài riêng của lớp)"><i class="ti ti-sparkles"></i></a>
                    @endif
                </div>
                @forelse ($l->exercises as $e)
                    <div class="d-flex align-items-center gap-2 small ms-3 mt-1">
                        <i class="ti ti-checklist text-muted"></i>
                        <span class="flex-fill">{{ $e->title }}
                            @if ($e->source_classroom_id)<span class="badge bg-orange-lt">riêng của lớp</span>@endif
                            @if ($e->status === 'draft')<span class="badge bg-secondary-lt">nháp</span>@endif</span>
                        <span class="text-muted">{{ (int) ($submitted[$e->id] ?? 0) }} bài nộp</span>
                        @if ($e->status === 'published')<a href="{{ route($prefix.'.custom.open', [$classroom, $e]) }}" class="btn btn-sm btn-outline-secondary py-0">Bảng điểm</a>@endif
                        @if ($e->source_classroom_id && ! $isAdmin && $canManage)<a href="{{ route('teacher.custom.edit', [$classroom->course_id, $e]) }}" class="btn btn-sm btn-ghost-secondary py-0">Sửa</a>@endif
                    </div>
                @empty
                    <div class="small text-muted ms-3 mt-1">Chưa có bài tập.</div>
                @endforelse
            </div>
        @empty
            <div class="list-group-item text-muted">Khoá chưa có bài học.</div>
        @endforelse
    </div>
</div>
