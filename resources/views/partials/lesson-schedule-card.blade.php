{{-- Lịch mở bài học theo lớp. Biến: $classroom, $action
     Dạng danh sách xếp dọc (tên bài một dòng, giờ mở + ẩn ở dòng dưới) để vừa cột hẹp bên phải trang lớp. --}}
@php $lessons = $classroom->course?->lessons()->get() ?? collect(); @endphp
@if ($lessons->isNotEmpty())
    <form method="POST" action="{{ $action }}" class="card mb-3">@csrf @method('PUT')
        <div class="card-header"><h3 class="card-title">Lịch mở bài học</h3></div>
        <div class="list-group list-group-flush overflow-auto" style="max-height: 460px">
            @foreach ($lessons as $l)
                @php $s = $l->scheduleFor($classroom); $opens = $s && $s->opens_at ? \Illuminate\Support\Carbon::parse($s->opens_at) : null; @endphp
                <div class="list-group-item py-2">
                    <div class="small fw-semibold mb-1">{{ $loop->iteration }}. {{ $l->title }}
                        @unless ($l->is_published) <span class="badge bg-secondary-lt">ẩn ở khoá</span>@endunless
                        @if ($s && $s->hidden)<span class="badge bg-red-lt">đang ẩn với lớp</span>
                        @elseif ($opens && $opens->isFuture())<span class="badge bg-yellow-lt">mở {{ $opens->format('H:i d/m') }}</span>
                        @else<span class="badge bg-green-lt">đang mở</span>@endif
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="datetime-local" name="lessons[{{ $l->id }}][opens_at]" class="form-control form-control-sm" value="{{ $opens ? $opens->format('Y-m-d\TH:i') : '' }}" title="Mở lúc (để trống = mở ngay)">
                        <label class="form-check mb-0 text-nowrap small"><input type="checkbox" class="form-check-input" name="lessons[{{ $l->id }}][hidden]" value="1" @checked($s && $s->hidden)><span class="form-check-label">Ẩn</span></label>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="card-footer d-flex align-items-center gap-2">
            <span class="small text-muted">Để trống giờ = mở ngay.</span>
            <button class="btn btn-sm btn-primary ms-auto">Lưu lịch</button>
        </div>
    </form>
@endif
