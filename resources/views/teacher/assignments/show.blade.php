@extends('layouts.teacher')

@section('title', $assignment->title)
@section('pretitle', $assignment->classroom->name.' · '.\App\Models\Assignment::MODES[$assignment->mode])

@php
    $skills = array_column($assignment->sections, 'skill');
    $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $done = $rows->filter(fn ($r) => $r['test'])->count();
    $avg = $rows->pluck('test')->filter(fn ($t) => $t && $t->status === 'completed')->avg('total_score');
@endphp

@section('actions')
    <div class="d-flex gap-2">
        <a href="{{ route('teacher.assignments.export', $assignment) }}" class="btn btn-outline-primary"><i class="ti ti-file-spreadsheet me-1"></i>Xuất Excel</a>
        <form method="POST" action="{{ route('teacher.assignments.destroy', $assignment) }}" onsubmit="return confirm('Xoá bài giao này?')">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="ti ti-trash"></i></button></form>
    </div>
@endsection

@section('content')
    <div class="row row-cards mb-3">
        <div class="col-sm-3"><div class="card card-sm"><div class="card-body"><div class="text-muted small">Đã nộp</div><div class="h2 mb-0">{{ $done }}/{{ $rows->count() }}</div></div></div></div>
        <div class="col-sm-3"><div class="card card-sm"><div class="card-body"><div class="text-muted small">Điểm trung bình</div><div class="h2 mb-0">{{ $fmt($avg) }}</div></div></div></div>
        <div class="col-sm-6"><div class="card card-sm"><div class="card-body small text-muted">
            {{ $assignment->unit->title }} · {{ collect($skills)->map(fn ($s) => \App\Models\ExerciseSet::SKILLS[$s])->implode(', ') }}<br>
            Mở {{ $assignment->opens_at?->format('H:i d/m') ?? 'ngay' }} → hạn {{ $assignment->due_at?->format('H:i d/m/Y') ?? 'không hạn' }}
            @if ($assignment->time_limit_minutes) · {{ $assignment->time_limit_minutes }} phút @endif
            @if ($assignment->max_attempts) · {{ $assignment->max_attempts }} lượt @endif
            @if ($assignment->max_listens) · nghe {{ $assignment->max_listens }} lần @endif
            · đáp án {{ $assignment->show_answers === 'after_due' ? 'sau hạn nộp' : 'ngay khi nộp' }}
        </div></div></div>
    </div>

    @if ($review->isNotEmpty())
        <div class="card mb-3 border-warning">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-eye-check me-1"></i>Bài Nói / Viết chờ giáo viên xem ({{ $review->count() }})</h3></div>
            <div class="list-group list-group-flush">
                @foreach ($review->take(15) as $a)
                    @php $flagged = collect($a->result)->contains(fn ($r) => ! empty($r['needs_review'])); @endphp
                    <a href="{{ route('teacher.assignments.review', [$assignment, $a->unit_test_id]) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center">
                        <span class="badge {{ $a->set->skill === 'speaking' ? 'bg-orange' : 'bg-green' }} text-white">{{ \App\Models\ExerciseSet::SKILLS[$a->set->skill] }}</span>
                        <span class="flex-fill">{{ $a->student->full_name ?: $a->student->email }}</span>
                        @if ($flagged)<span class="badge bg-red-lt">Máy gắn cờ</span>@endif
                        <span class="text-muted">Máy chấm {{ $fmt($a->score) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Bảng điểm (lần làm tốt nhất)</h3></div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>Học viên</th><th class="text-center">Lần làm</th><th class="text-center">Điểm tổng</th><th>Trình độ</th>
                    @foreach ($skills as $s)<th class="text-center">{{ \App\Models\ExerciseSet::SKILLS[$s] }}</th>@endforeach
                    <th>Nộp lúc</th><th class="w-1"></th></tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        @php $t = $r['test']; @endphp
                        <tr>
                            <td>{{ $r['student']->full_name ?: '—' }}<div class="small text-muted">{{ $r['student']->email }}</div></td>
                            <td class="text-center">{{ $r['count'] ?: '—' }}</td>
                            <td class="text-center fw-bold">{{ $t?->status === 'completed' ? $fmt($t->total_score) : ($t ? 'Đang chấm' : '—') }}</td>
                            <td>{!! $t?->level ? '<span class="badge bg-blue-lt">'.e($t->level).'</span>' : ($t ? '' : '<span class="badge bg-secondary-lt">Chưa làm</span>') !!}</td>
                            @foreach ($skills as $s)<td class="text-center">{{ $fmt($t?->skill_scores[$s] ?? null) }}</td>@endforeach
                            <td class="small text-muted">{{ $t?->finished_at?->format('H:i d/m') }}</td>
                            <td>@if ($t)<a href="{{ route('teacher.assignments.review', [$assignment, $t]) }}" class="btn btn-sm btn-outline-secondary">Xem bài</a>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($wrong->isNotEmpty())
        <div class="card">
            <div class="card-header"><h3 class="card-title">Câu nhiều người sai</h3></div>
            <div class="table-responsive">
                <table class="table table-sm card-table">
                    <thead><tr><th>Câu</th><th>Nội dung</th><th>Đáp án</th><th class="text-end">Sai</th></tr></thead>
                    @foreach ($wrong as $w)
                        <tr>
                            <td>{{ $w['code'] }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($w['prompt'], 120) }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($w['answer'], 60) }}</td>
                            <td class="text-end"><span class="badge bg-red-lt">{{ $w['wrong'] }}/{{ $w['total'] }}</span></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    @endif
@endsection
