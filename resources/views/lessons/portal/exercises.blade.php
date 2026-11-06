{{-- Bài tập của bài học (trang bài học của học viên). Biến: $classroom, $lesson, $videosDone --}}
@php
    $sid = auth('student')->id();
    $exercises = $lesson->exercisesFor($classroom)->withCount('items')->get();
    $settings = \App\Models\ClassroomCustomExercise::where('classroom_id', $classroom->id)->whereIn('custom_exercise_id', $exercises->pluck('id'))
        ->with(['attempts' => fn ($q) => $q->where('student_id', $sid)->latest('id')])->get()->keyBy('custom_exercise_id');
    $locked = $lesson->require_video && ! $videosDone;
@endphp
<div class="card border-0 shadow-sm mb-3" id="lesson-exercises">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-ui-checks"></i> Bài tập của bài học ({{ $exercises->count() }})</div>
    <div class="list-group list-group-flush">
        @forelse ($exercises as $e)
            @php
                $cce = $settings[$e->id] ?? null;
                $attempts = $cce?->attempts ?? collect();
                $done = $attempts->whereIn('status', ['graded', 'grading']);
                $best = $done->max(fn ($a) => $a->finalScore());
                $open = ! $cce || $cce->isOpen();
                $left = $cce && $cce->max_attempts ? $cce->max_attempts - $attempts->count() : null;
            @endphp
            <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
                <div class="flex-fill">
                    <div class="fw-semibold">{{ $e->title }}</div>
                    <div class="small text-muted">{{ $e->items_count }} phần · {{ \App\Models\CustomExercise::SKILLS[$e->skill] ?? '' }}
                        @if ($cce?->due_at) · Hạn {{ $cce->due_at->format('H:i d/m/Y') }}@endif
                        @if ($cce?->timeLimit()) · {{ $cce->timeLimit() }} phút @endif</div>
                </div>
                @if ($best !== null)<span class="badge text-bg-success">{{ number_format($best, 1) }}/10</span>@endif
                @if ($done->isNotEmpty())<a href="{{ route('portal.custom.result', $done->first()) }}" class="btn btn-sm btn-outline-secondary">Kết quả</a>@endif
                @if ($locked)
                    <span class="small text-warning lesson-lock"><i class="bi bi-lock"></i> Xem xong video để mở</span>
                @elseif ($open && ($left === null || $left > 0 || $attempts->firstWhere('status', 'in_progress')))
                    <form method="POST" action="{{ route('portal.custom.start-exercise', [$classroom, $e]) }}">@csrf
                        <button class="btn btn-sm btn-primary">{{ $attempts->firstWhere('status', 'in_progress') ? 'Làm tiếp' : ($done->isNotEmpty() ? 'Làm lại' : 'Làm bài') }}</button>
                    </form>
                @elseif ($cce?->isClosed())
                    <span class="badge text-bg-danger">Đã hết hạn</span>
                @endif
            </div>
        @empty
            <div class="list-group-item text-muted small">Bài học chưa có bài tập.</div>
        @endforelse
    </div>
</div>
@if ($locked)
    @push('scripts')<script>document.addEventListener('lesson-videos-done', () => location.reload(), { once: true });</script>@endpush
@endif
