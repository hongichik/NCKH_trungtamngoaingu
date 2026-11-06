@extends($layout)

@section('title', $cce->exercise->title.' — '.$classroom->name)

@section('content')
    <div class="mb-3"><a href="{{ route($prefix.'.show', $classroom) }}"><i class="ti ti-arrow-left me-1"></i>{{ $classroom->name }}</a></div>
    @php $fmt = fn ($v) => $v === null ? '—' : number_format((float) $v, 1); @endphp
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Bảng điểm</h3>
                    <div class="card-actions small text-muted">{{ $attempts->count() }}/{{ $students->count() }} học viên đã nộp</div></div>
                <div class="table-responsive">
                    <table class="table table-sm table-vcenter card-table">
                        <thead><tr><th>Học viên</th><th class="text-end">Lượt</th><th class="text-end">Điểm cao nhất</th><th>Lần nộp gần nhất</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($students as $st)
                                @php $list = $attempts[$st->id] ?? collect(); $last = $list->sortByDesc('id')->first(); @endphp
                                <tr>
                                    <td>{{ $st->full_name }}</td>
                                    <td class="text-end">{{ $list->count() }}</td>
                                    <td class="text-end fw-semibold">{{ $list->isNotEmpty() ? $fmt($list->max(fn ($a) => $a->finalScore())) : '—' }}</td>
                                    <td class="small text-muted">{{ $last?->submitted_at?->format('H:i d/m/Y') ?? 'Chưa nộp' }}
                                        @if ($last?->status === 'grading')<span class="badge bg-yellow-lt">chờ chấm</span>@endif
                                        @if ($last?->teacher_score !== null)<span class="badge bg-blue-lt">GV đã sửa</span>@endif</td>
                                    <td>@if ($last)<a href="{{ route($prefix.'.custom.review', [$classroom, $cce, $last]) }}" class="btn btn-sm btn-outline-primary">Xem / chấm</a>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <form method="POST" action="{{ route($prefix.'.custom.update', [$classroom, $cce]) }}" class="card">@csrf @method('PUT')
                <div class="card-header"><h3 class="card-title">Cài đặt bài trong lớp</h3></div>
                <div class="card-body">
                    @php $f = fn ($d) => $d?->format('Y-m-d\TH:i'); @endphp
                    <div class="mb-2"><label class="form-label">Mở lúc</label><input type="datetime-local" name="opens_at" class="form-control" value="{{ $f($cce->opens_at) }}"></div>
                    <div class="mb-2"><label class="form-label">Hạn nộp</label><input type="datetime-local" name="due_at" class="form-control" value="{{ $f($cce->due_at) }}"></div>
                    <div class="mb-2"><label class="form-label">Số lượt tối đa</label><input type="number" name="max_attempts" class="form-control" value="{{ $cce->max_attempts }}"></div>
                    <div class="mb-2"><label class="form-label">Giới hạn thời gian (phút)</label><input type="number" name="time_limit_minutes" class="form-control" value="{{ $cce->time_limit_minutes }}"></div>
                    <div class="mb-2"><label class="form-label">Hiện đáp án</label><select name="show_answers" class="form-select">@foreach (\App\Models\ClassroomCustomExercise::SHOW_ANSWERS as $k => $l)<option value="{{ $k }}" @selected($cce->show_answers === $k)>{{ $l }}</option>@endforeach</select></div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary btn-sm">Lưu</button></div>
            </form>
        </div>
    </div>
@endsection
