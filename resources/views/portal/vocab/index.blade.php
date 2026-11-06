@extends('layouts.portal')

@section('title', 'Ôn từ vựng')

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Ôn từ vựng</h1>
        @if ($due || $fresh)
            <a href="{{ route('portal.vocab.study') }}" class="btn btn-primary ms-auto"><i class="bi bi-lightning-charge"></i> Ôn ngay
                @if ($due)<span class="badge text-bg-light ms-1">{{ $due }} thẻ đến hạn</span>@endif</a>
        @endif
    </div>
    <p class="text-muted small">Thẻ từ lấy từ các bài học đang mở trong lớp của bạn (và các Unit giáo viên đã giao). Mỗi lần ôn bạn tự đánh giá <strong>Quên / Khó / Nhớ / Dễ</strong>; từ càng nhớ chắc thì càng lâu mới phải ôn lại (lặp lại ngắt quãng). Mỗi lượt học thêm tối đa {{ \App\Support\Vocab\Flashcards::NEW_PER_SESSION }} từ mới.</p>

    @if ($summary->isEmpty())
        <div class="alert alert-info">Chưa có từ vựng để ôn. Khi bài học trong lớp có danh sách từ vựng (giáo viên lập), các từ sẽ xuất hiện ở đây.</div>
    @else
        <div class="row g-3">
            @foreach ($summary as $s)
                @php $u = $s['unit']; $pct = $s['total'] ? round($s['mature'] / $s['total'] * 100) : 0; @endphp
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body d-flex flex-column">
                            <div class="small text-muted">{{ $u->subtitle }}</div>
                            <h2 class="h6">{{ $u->title }}</h2>
                            <div class="small mb-2">{{ $s['total'] }} từ · đã học {{ $s['learned'] }} · đã thuộc {{ $s['mature'] }}
                                @if ($s['due'])<span class="badge text-bg-danger ms-1">{{ $s['due'] }} đến hạn</span>@endif</div>
                            <div class="progress mb-3" style="height: 6px" title="Đã thuộc {{ $pct }}%"><div class="progress-bar bg-success" style="width: {{ $pct }}%"></div></div>
                            <div class="d-flex flex-wrap gap-2 mt-auto">
                                <a href="{{ route('portal.vocab.study', ['unit' => $u->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-card-heading"></i> Học thẻ</a>
                                @if ($u->isChinese())
                                    <a href="{{ route('portal.vocab.drill', $u->id) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-ui-radios"></i> Luyện thanh điệu & chữ</a>
                                    <a href="{{ route('portal.vocab.writing', $u->id) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-brush"></i> Tập viết chữ Hán</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
