{{-- Thời hạn lớp + trạng thái (dùng ở trang quản trị, giáo viên, học viên). Biến: $classroom, $student (tuỳ chọn: giọng văn cho học viên) --}}
@php
    $st = $classroom->status();
    $cls = ['upcoming' => 'alert-info', 'active' => 'alert-success', 'ended' => 'alert-secondary'][$st];
    $until = $classroom->accessUntil();
@endphp
@if ($classroom->starts_at || $classroom->ends_at)
    <div class="alert {{ $cls }} py-2 mb-3">
        <strong>{{ \App\Models\Classroom::STATUSES[$st] }}</strong> ·
        {{ $classroom->starts_at?->format('H:i d/m/Y') ?? 'Mở ngay' }} → {{ $classroom->ends_at?->format('H:i d/m/Y') ?? 'không giới hạn' }}
        @if ($st === 'ended')
            @if ($classroom->accessOpen())
                · Vẫn cho vào học {{ $until ? 'đến '.$until->format('d/m/Y') : '(không giới hạn)' }}
            @else
                · {{ ! empty($student) ? 'Bạn vẫn xem được điểm và lịch sử bài làm, nhưng không vào học / làm bài được nữa.' : 'Học viên chỉ còn xem được điểm.' }}
            @endif
        @elseif ($st === 'upcoming' && ! empty($student))
            · Lớp chưa bắt đầu, bạn chưa làm bài được.
        @endif
    </div>
@endif
