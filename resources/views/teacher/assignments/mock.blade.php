@extends('layouts.teacher')

@section('title', 'Đề thi thử')
@section('pretitle', $classroom->name)

@php $skills = \App\Models\ExerciseSet::SKILLS; @endphp

@section('content')
    <form method="POST" action="{{ route('teacher.assignments.mock.store', $classroom) }}" class="card" style="max-width: 900px">@csrf
        <div class="card-body">
            <p class="text-muted">Máy ghép ngẫu nhiên các bộ bài tập <strong>đã xuất bản</strong> của các Unit bạn chọn thành 1 đề có tính giờ (không tốn AI). Mỗi Unit góp tối đa 1 bộ mỗi kỹ năng.</p>
            <div class="mb-3"><label class="form-label required">Tên đề</label><input name="title" class="form-control" value="{{ old('title', 'Đề thi thử '.now()->format('d/m')) }}" required></div>

            <div class="mb-3">
                <label class="form-label required">Lấy từ các Unit</label>
                <div class="row g-1">
                    @foreach ($units as $u)
                        @php $p = ($pool[$u->id] ?? collect())->pluck('n', 'skill'); @endphp
                        <div class="col-md-6">
                            <label class="form-check mb-0 {{ $p->isEmpty() ? 'opacity-50' : '' }}">
                                <input class="form-check-input" type="checkbox" name="unit_ids[]" value="{{ $u->id }}" @checked($p->isNotEmpty()) @disabled($p->isEmpty())>
                                <span class="form-check-label">{{ $u->title }} <span class="text-muted small">— {{ $p->isEmpty() ? 'chưa có bộ xuất bản' : $p->keys()->map(fn ($k) => $skills[$k])->implode(', ') }}</span></span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Số bộ mỗi kỹ năng</label>
                <div class="row g-2">
                    @foreach ($skills as $k => $label)
                        <div class="col-6 col-md-3"><div class="input-group input-group-sm"><span class="input-group-text">{{ $label }}</span>
                            <input type="number" min="0" max="5" name="counts[{{ $k }}]" class="form-control" value="{{ old('counts.'.$k, in_array($k, ['reading', 'listening'], true) ? 2 : 1) }}"></div></div>
                    @endforeach
                </div>
                <div class="form-hint">0 = bỏ kỹ năng đó. Thiếu bộ thì lấy hết số hiện có.</div>
            </div>

            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Thời gian làm (phút)</label><input type="number" name="time_limit_minutes" min="5" max="600" class="form-control" value="{{ old('time_limit_minutes', 60) }}"></div>
                <div class="col-md-4"><label class="form-label">Mở lúc</label><input type="datetime-local" name="opens_at" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Hạn nộp</label><input type="datetime-local" name="due_at" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Số lượt làm</label><input type="number" name="max_attempts" min="1" max="20" class="form-control" value="1"></div>
                <div class="col-md-4"><label class="form-label">Số lần nghe tối đa</label><input type="number" name="max_listens" min="1" max="10" class="form-control" value="2"></div>
                <div class="col-md-4"><label class="form-label">Xem đáp án</label>
                    <select name="show_answers" class="form-select"><option value="after_due">Sau hạn nộp</option><option value="after_submit">Ngay sau khi nộp</option></select></div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <a href="{{ route('teacher.classrooms.show', $classroom) }}" class="btn btn-outline-secondary">Huỷ</a>
            <button class="btn btn-primary ms-auto"><i class="ti ti-clipboard-list me-1"></i>Tạo & giao đề</button>
        </div>
    </form>
@endsection
