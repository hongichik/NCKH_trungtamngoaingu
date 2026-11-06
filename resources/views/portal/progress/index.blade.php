@extends('layouts.portal')

@section('title', 'Tiến độ & huy hiệu')

@php
    $goal = $stat->daily_goal;
    $pct = min(100, round($today / max(1, $goal) * 100));
    $maxDay = max(1, max($daily));
@endphp

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Tiến độ & huy hiệu</h1>
        <a href="{{ route('portal.progress.report') }}" class="btn btn-outline-secondary btn-sm ms-auto" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Báo cáo (in / lưu PDF)</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card shadow-sm border-0 h-100"><div class="card-body">
            <div class="text-muted small">Tổng điểm kinh nghiệm</div><div class="fs-3 fw-bold"><i class="bi bi-lightning-charge-fill text-warning"></i> {{ number_format($stat->xp) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card shadow-sm border-0 h-100"><div class="card-body">
            <div class="text-muted small">Chuỗi ngày học</div><div class="fs-3 fw-bold"><i class="bi bi-fire text-danger"></i> {{ $streak }} ngày</div><div class="small text-muted">Kỷ lục: {{ $stat->best_streak }} ngày</div></div></div></div>
        <div class="col-lg-6"><div class="card shadow-sm border-0 h-100"><div class="card-body">
            <div class="d-flex align-items-center"><div class="text-muted small">Mục tiêu hôm nay</div><span class="ms-auto small fw-semibold">{{ $today }}/{{ $goal }} XP</span></div>
            <div class="progress my-2" style="height: 10px"><div class="progress-bar {{ $pct >= 100 ? 'bg-success' : '' }}" style="width: {{ $pct }}%"></div></div>
            <form method="POST" action="{{ route('portal.progress.goal') }}" class="d-flex flex-wrap gap-1 align-items-center">@csrf
                <span class="small text-muted me-1">Đổi mục tiêu:</span>
                @foreach (\App\Support\Progress\Gamification::GOALS as $xp => $label)
                    <button name="daily_goal" value="{{ $xp }}" class="btn btn-sm {{ $goal === $xp ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }} · {{ $xp }}</button>
                @endforeach
            </form>
        </div></div></div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold">XP 14 ngày gần đây</div>
        <div class="card-body">
            <div class="d-flex align-items-end gap-1" style="height: 120px">
                @foreach ($daily as $d => $xp)
                    <div class="flex-fill text-center" title="{{ \Carbon\Carbon::parse($d)->format('d/m') }}: {{ $xp }} XP">
                        <div class="small text-muted" style="font-size: .65rem">{{ $xp ?: '' }}</div>
                        <div class="rounded-top {{ $xp >= $goal ? 'bg-success' : 'bg-primary' }}" style="height: {{ max(2, round($xp / $maxDay * 90)) }}px; opacity: {{ $xp ? 1 : .2 }}"></div>
                    </div>
                @endforeach
            </div>
            <div class="d-flex justify-content-between small text-muted mt-1"><span>{{ \Carbon\Carbon::parse(array_key_first($daily))->format('d/m') }}</span><span>Hôm nay</span></div>
            <div class="small text-muted mt-2">Cách nhận XP: làm bài được giao (10 + điểm × 5), ôn thẻ từ (1–2 / thẻ), luyện tiếng Trung, tập viết chữ Hán, sửa lỗi sai, bài luyện riêng. Mỗi loại có giới hạn XP mỗi ngày.</div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold">Huy hiệu ({{ $badges->count() }}/{{ count(\App\Support\Progress\Gamification::BADGES) }})</div>
        <div class="card-body">
            <div class="row g-2">
                @foreach (\App\Support\Progress\Gamification::BADGES as $code => [$name, $desc, $icon, $color])
                    @php $got = $badges->get($code); @endphp
                    <div class="col-6 col-md-4 col-xl-3">
                        <div class="d-flex align-items-center gap-2 p-2 rounded border {{ $got ? '' : 'opacity-50' }}" title="{{ $desc }}">
                            <span class="fs-3 text-{{ $got ? $color : 'secondary' }}"><i class="bi bi-{{ $icon }}"></i></span>
                            <div class="small lh-sm"><div class="fw-semibold">{{ $name }}@if ($got && $got->count() > 1) ×{{ $got->count() }}@endif</div><div class="text-muted">{{ $got ? 'Đạt '.$got->first()->earned_at->format('d/m/Y') : $desc }}</div></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold">Bản đồ năng lực</div>
        <div class="card-body">@include('portal.progress.partials.competency')</div>
    </div>

    @foreach ($boards as $b)
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-bar-chart-steps"></i> Bảng xếp hạng 7 ngày — {{ $b['classroom']->name }}</div>
            <ol class="list-group list-group-flush list-group-numbered">
                @foreach ($b['rows'] as $r)
                    <li class="list-group-item d-flex {{ $r['student']->id === $student->id ? 'fw-semibold bg-light' : '' }}"><span class="ms-2">{{ $r['student']->full_name ?: 'Học viên' }}</span><span class="ms-auto">{{ $r['xp'] }} XP</span></li>
                @endforeach
            </ol>
        </div>
    @endforeach
@endsection
