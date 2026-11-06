<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Báo cáo học tập — {{ $student->full_name }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #fff; color: #111; }
        .sheet { max-width: 900px; margin: 0 auto; padding: 24px 16px; }
        @media print { .no-print { display: none !important; } .sheet { padding: 0; } @page { size: A4; margin: 14mm; } .table td, .table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="no-print d-flex gap-2 mb-3">
        <button onclick="window.print()" class="btn btn-primary">In / Lưu PDF</button>
        <a href="{{ route('portal.progress.index') }}" class="btn btn-outline-secondary">Quay lại</a>
        <span class="small text-muted align-self-center">Chọn “Lưu thành PDF” trong hộp thoại in để tải file PDF.</span>
    </div>
    <div class="d-flex align-items-end border-bottom pb-2 mb-3">
        <div>
            <div class="small text-muted text-uppercase">{{ config('app.name') }}</div>
            <h1 class="h4 mb-0">Báo cáo học tập</h1>
        </div>
        <div class="ms-auto text-end small">{{ $student->full_name }}<br>{{ $student->email }}<br>Ngày lập: {{ now()->format('d/m/Y') }}</div>
    </div>

    <div class="row text-center mb-3">
        <div class="col"><div class="fs-4 fw-bold">{{ number_format($stat->xp) }}</div><div class="small text-muted">Tổng XP</div></div>
        <div class="col"><div class="fs-4 fw-bold">{{ $streak }}</div><div class="small text-muted">Chuỗi ngày hiện tại</div></div>
        <div class="col"><div class="fs-4 fw-bold">{{ $stat->best_streak }}</div><div class="small text-muted">Chuỗi dài nhất</div></div>
        <div class="col"><div class="fs-4 fw-bold">{{ $competency->count() }}</div><div class="small text-muted">Unit đã làm</div></div>
        <div class="col"><div class="fs-4 fw-bold">{{ $badges->count() }}</div><div class="small text-muted">Huy hiệu</div></div>
        <div class="col"><div class="fs-4 fw-bold">{{ $mistakeCount }}</div><div class="small text-muted">Lỗi chưa sửa</div></div>
    </div>

    <h2 class="h6">Bản đồ năng lực (điểm cao nhất theo Unit và kỹ năng, thang 10)</h2>
    @include('portal.progress.partials.competency')

    @if ($badges->isNotEmpty())
        <h2 class="h6 mt-4">Huy hiệu đã đạt</h2>
        <ul class="small">
            @foreach ($badges as $code => $list)
                <li>{{ \App\Support\Progress\Gamification::BADGES[$code][0] ?? $code }} — {{ \App\Support\Progress\Gamification::BADGES[$code][1] ?? '' }} ({{ $list->first()->earned_at->format('d/m/Y') }})</li>
            @endforeach
        </ul>
    @endif

    <h2 class="h6 mt-4">Nhận xét tự động</h2>
    @php
        $skills = \App\Models\ExerciseSet::SKILLS;
        $avg = [];
        foreach ($competency as $row) { foreach ($row['skills'] as $sk => $v) { $avg[$sk][] = $v; } }
        $avg = array_map(fn ($a) => array_sum($a) / count($a), $avg);
        arsort($avg);
    @endphp
    @if ($avg)
        <p class="small mb-1">Kỹ năng mạnh nhất: <strong>{{ $skills[array_key_first($avg)] }}</strong> ({{ number_format(reset($avg), 1) }}). Cần luyện thêm: <strong>{{ $skills[array_key_last($avg)] }}</strong> ({{ number_format(end($avg), 1) }}).</p>
    @else
        <p class="small">Chưa đủ dữ liệu để nhận xét.</p>
    @endif
</div>
</body>
</html>
