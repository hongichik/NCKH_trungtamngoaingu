@extends('layouts.teacher')

@section('title', 'Bản đồ năng lực · '.$classroom->name)
@section('pretitle', 'Lớp học')

@php
    $skills = \App\Models\ExerciseSet::SKILLS;
    // Bài tập trong bài học có thêm nhóm "Từ vựng & ngữ pháp"
    $hasVocab = isset($competency) ? $competency->contains(fn ($r) => isset($r['skills']['vocab'])) : collect($skillAvg ?? [])->contains(fn ($r) => isset($r['vocab']));
    if ($hasVocab) { $skills += ['vocab' => 'Từ vựng & NP']; }
    $cell = fn ($v) => $v === null ? 'text-muted' : ($v >= 8 ? 'bg-green text-white' : ($v >= 6.5 ? 'bg-blue text-white' : ($v >= 5 ? 'bg-yellow' : 'bg-red text-white')));
    $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.');
@endphp

@section('actions')
    <a href="{{ route('teacher.classrooms.show', $classroom) }}" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>Lớp học</a>
@endsection

@section('content')
    @if ($units->isEmpty())
        <div class="alert alert-info">Chưa có bài làm nào được chấm xong trong lớp.</div>
    @else
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Trung bình lớp theo Unit / bài học × kỹ năng</h3></div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered card-table text-center">
                    <thead><tr><th class="text-start">Unit / Bài học</th>@foreach ($skills as $l)<th>{{ $l }}</th>@endforeach</tr></thead>
                    @foreach ($units as $u)
                        <tr><td class="text-start">{{ $u->title }}</td>@foreach ($skills as $sk => $l)@php $v = $skillAvg[$u->id][$sk] ?? null; @endphp<td class="{{ $cell($v) }}">{{ $fmt($v) }}</td>@endforeach</tr>
                    @endforeach
                </table>
            </div>
            <div class="card-footer small text-muted">Ô đỏ / vàng: kỹ năng cả lớp còn yếu ở Unit đó → nên giao thêm bài luyện.</div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Học viên × Unit / bài học (điểm tổng cao nhất)</h3></div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered card-table text-center">
                    <thead><tr><th class="text-start">Học viên</th>@foreach ($units as $u)<th class="small">{{ \Illuminate\Support\Str::limit($u->title, 22) }}</th>@endforeach<th>TB</th></tr></thead>
                    @foreach ($classroom->students as $st)
                        @php $row = $grid[$st->id] ?? []; $avg = $row ? array_sum($row) / count($row) : null; @endphp
                        <tr><td class="text-start">{{ $st->full_name }}</td>@foreach ($units as $u)@php $v = $row[$u->id] ?? null; @endphp<td class="{{ $cell($v) }}">{{ $fmt($v) }}</td>@endforeach<td class="fw-bold">{{ $fmt($avg) }}</td></tr>
                    @endforeach
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">Điểm kinh nghiệm 7 ngày</h3><div class="card-actions small text-muted">Học viên {{ $classroom->show_leaderboard ? 'đang thấy' : 'không thấy' }} bảng xếp hạng (bật/tắt ở trang lớp)</div></div>
        <div class="table-responsive">
            <table class="table table-sm card-table">
                @foreach ($board as $i => $r)<tr><td class="w-1 text-muted">{{ $i + 1 }}</td><td>{{ $r['student']->full_name }}</td><td class="text-end">{{ $r['xp'] }} XP</td></tr>@endforeach
            </table>
        </div>
    </div>
@endsection
