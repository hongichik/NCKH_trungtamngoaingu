@extends('layouts.teacher')

@section('title', ($test->student->full_name ?: $test->student->email))
@section('pretitle', $assignment->title.' · bài làm lúc '.($test->finished_at?->format('H:i d/m/Y') ?? $test->updated_at->format('H:i d/m/Y')))

@php
    $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $labels = ['task_achievement' => 'Hoàn thành nhiệm vụ', 'pronunciation' => 'Phát âm', 'fluency' => 'Trôi chảy', 'vocabulary' => 'Từ vựng', 'grammar' => 'Ngữ pháp', 'coherence' => 'Mạch lạc'];
@endphp

@section('actions')
    <a href="{{ route('teacher.assignments.show', $assignment) }}" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>Bảng điểm</a>
@endsection

@section('content')
    <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-4 align-items-center">
        <div><div class="text-muted small">Điểm tổng</div><div class="h1 mb-0">{{ $test->status === 'completed' ? $fmt($test->total_score) : 'Đang chấm' }}</div></div>
        <div><div class="text-muted small">Trình độ</div><div class="h3 mb-0">{{ $test->level ?? '—' }}</div></div>
        <div class="small text-muted">Sửa điểm từng phần ở dưới → điểm tổng và trình độ được tính lại.</div>
    </div></div>

    @foreach ($test->sections as $sec)
        @php $a = $attempts[$sec['attempt_id']] ?? null; @endphp
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">{{ \App\Models\ExerciseSet::SKILLS[$sec['skill']] }}</h3>
                <div class="card-actions">
                    @if ($a)
                        Máy chấm <strong>{{ $fmt($a->score) }}</strong>
                        @if ($a->teacher_score !== null) · Giáo viên <strong class="text-primary">{{ $fmt($a->teacher_score) }}</strong>@endif
                    @else <span class="text-muted">Chưa làm</span> @endif
                </div>
            </div>
            @if ($a)
                <div class="list-group list-group-flush">
                    @foreach ($a->result ?? [] as $r)
                        <div class="list-group-item">
                            <div class="d-flex gap-2">
                                <span class="badge {{ $r['correct'] ? 'bg-success-lt' : 'bg-danger-lt' }}">{{ $r['code'] }}</span>
                                <div class="flex-fill small">
                                    <div class="fw-semibold">{{ \Illuminate\Support\Str::limit($r['prompt'], 300) }}</div>
                                    @if (in_array($r['type'], ['speaking', 'read_aloud'], true))
                                        @if (! empty($a->files[$r['code']]['path']))
                                            <audio controls preload="none" src="{{ route('teacher.assignments.recording', [$assignment, $a, $r['code']]) }}" class="mt-1" style="height: 32px"></audio>
                                        @elseif (! empty($a->files[$r['code']]['pruned_at']))
                                            <div class="text-muted">Ghi âm đã tự xoá ngày {{ \Carbon\Carbon::parse($a->files[$r['code']]['pruned_at'])->format('d/m/Y') }} (giữ {{ config('ai.recordings_keep_days') }} ngày).</div>
                                        @else <div class="text-muted">Không có ghi âm.</div> @endif
                                        @if (! empty($r['transcript']))<div class="text-muted">Máy chép: “{{ $r['transcript'] }}”</div>@endif
                                    @elseif ($r['type'] === 'paragraph')
                                        <div class="p-2 bg-light rounded text-pre mt-1">{{ $r['given'] ?: '(bỏ trống)' }}</div>
                                    @else
                                        <div>Trả lời: <strong>{{ $r['given'] !== '' ? $r['given'] : '(bỏ trống)' }}</strong> @unless ($r['correct'])· Đáp án: <strong class="text-success">{{ $r['answer'] }}</strong>@endunless</div>
                                    @endif
                                    @if (! empty($r['scores']))
                                        <div class="mt-1">@foreach ($r['scores'] as $k => $v)<span class="badge bg-secondary-lt me-1">{{ $labels[$k] ?? $k }} {{ $fmt($v) }}</span>@endforeach</div>
                                    @endif
                                    @if (! empty($r['feedback_vi']))<div class="text-muted mt-1">AI: {{ $r['feedback_vi'] }}</div>@endif
                                    @if (! empty($r['comment_vi']))<div class="text-muted mt-1">AI: {{ $r['comment_vi'] }}</div>@endif
                                    @if (! empty($r['needs_review']))<span class="badge bg-red-lt mt-1">Máy gắn cờ: {{ $r['status'] ?? '' }} {{ implode(', ', $r['flags'] ?? []) }}</span>@endif
                                </div>
                                @if (in_array($r['type'], ['speaking', 'read_aloud', 'paragraph'], true))<div class="fw-bold">{{ $fmt($r['score'] ?? 0) }}</div>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('teacher.assignments.review.save', [$assignment, $a]) }}" class="card-footer">@csrf @method('PUT')
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2"><label class="form-label small mb-0">Điểm giáo viên (0–10)</label><input type="number" name="teacher_score" step="0.1" min="0" max="10" class="form-control form-control-sm" value="{{ $a->teacher_score }}" placeholder="{{ $fmt($a->score) }}"></div>
                        <div class="col-md-8"><label class="form-label small mb-0">Nhận xét cho học viên</label><input name="teacher_comment" class="form-control form-control-sm" value="{{ $a->teacher_comment }}"></div>
                        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">{{ $a->reviewed_at ? 'Cập nhật' : 'Lưu / Đã xem' }}</button></div>
                    </div>
                    <div class="form-hint">Để trống điểm = giữ điểm máy chấm (vẫn đánh dấu là giáo viên đã xem).</div>
                </form>
            @endif
        </div>
    @endforeach
@endsection
