@extends('layouts.admin')

@section('title', $classroom->exists ? 'Sửa lớp — '.$classroom->name : 'Tạo lớp học')

@section('content')
    @php
        $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('Y-m-d\TH:i') : '';
        $assigned = old('teachers') ? collect(old('teachers'))->filter(fn ($r) => ! empty($r['id']))->keyBy('id')->all()
            : $classroom->teachers->mapWithKeys(fn ($t) => [$t->id => ['id' => $t->id] + $t->pivot->only(array_keys(\App\Models\Classroom::PERMS))])->all();
    @endphp
    <form method="POST" action="{{ $classroom->exists ? route('admin.classrooms.update', $classroom) : route('admin.classrooms.store') }}" class="row g-3">
        @csrf @if ($classroom->exists) @method('PUT') @endif
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Thông tin lớp</h3></div>
                <div class="card-body row g-3">
                    <div class="col-12"><label class="form-label required">Tên lớp</label><input name="name" class="form-control" value="{{ old('name', $classroom->name) }}" required maxlength="120" placeholder="VD: Tiếng Anh B1 — Tối 2-4-6"></div>
                    <div class="col-12"><label class="form-label required">Khoá học</label>
                        <select name="course_id" class="form-select" required><option value="">— Chọn khoá —</option>
                            @foreach ($courses as $c)<option value="{{ $c->id }}" @selected((int) old('course_id', $classroom->course_id) === $c->id)>{{ $c->title }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">Bắt đầu</label><input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', $fmt($classroom->starts_at)) }}"></div>
                    <div class="col-md-6"><label class="form-label">Kết thúc</label><input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', $fmt($classroom->ends_at)) }}"></div>
                    <div class="col-12 form-hint mt-0">Trước giờ bắt đầu và sau giờ kết thúc, học viên không vào học / làm bài được, nhưng vẫn xem được điểm cũ.</div>
                    <div class="col-12"><label class="form-label">Mô tả</label><textarea name="description" class="form-control" rows="2">{{ old('description', $classroom->description) }}</textarea></div>
                    <div class="col-12 d-flex flex-wrap gap-3">
                        <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="allow_join" value="1" @checked(old('allow_join', $classroom->allow_join))><span class="form-check-label">Cho vào lớp bằng link mời</span></label>
                        @if ($classroom->exists)<label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="new_link" value="1"><span class="form-check-label">Tạo link mời mới</span></label>@endif
                        <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="show_leaderboard" value="1" @checked(old('show_leaderboard', $classroom->show_leaderboard))><span class="form-check-label">Bảng xếp hạng lớp</span></label>
                    </div>
                </div>
            </div>
            <div class="card mt-3 {{ $isOwner ? '' : 'opacity-75' }}">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-crown me-1"></i>Sau khi lớp kết thúc</h3></div>
                <div class="card-body">
                    @if ($isOwner)
                        <label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="allow_after_end" value="1" @checked(old('allow_after_end', $classroom->allow_after_end))><span class="form-check-label">Vẫn cho học viên vào học / làm bài</span></label>
                        <div class="input-group mt-2" style="max-width: 280px"><span class="input-group-text">Gia hạn</span><input type="number" name="grace_days" min="1" class="form-control" value="{{ old('grace_days', $classroom->grace_days) }}" placeholder="không giới hạn"><span class="input-group-text">ngày</span></div>
                    @else
                        <div class="text-muted">{{ $classroom->allow_after_end ? 'Đang cho vào sau khi kết thúc'.($classroom->grace_days ? ' thêm '.$classroom->grace_days.' ngày' : '') : 'Không cho vào sau khi kết thúc' }}. Chỉ chủ hệ thống đổi được.</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Giáo viên của lớp & quyền</h3></div>
                <div class="table-responsive" style="max-height: 520px">
                    <table class="table table-sm card-table table-vcenter">
                        <thead><tr><th>Giáo viên</th>@foreach (\App\Models\Classroom::PERMS as $label)<th class="text-center small">{{ $label }}</th>@endforeach</tr></thead>
                        <tbody>
                            @forelse ($teachers as $i => $t)
                                @php $row = $assigned[$t->id] ?? null; @endphp
                                <tr>
                                    <td><label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="teachers[{{ $i }}][id]" value="{{ $t->id }}" @checked($row)><span class="form-check-label">{{ $t->name }}</span></label></td>
                                    @foreach (array_keys(\App\Models\Classroom::PERMS) as $p)
                                        <td class="text-center"><input class="form-check-input" type="checkbox" name="teachers[{{ $i }}][{{ $p }}]" value="1" @checked($row ? ! empty($row[$p]) : $p === 'can_reset_password')></td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">Chưa có giáo viên. <a href="{{ route('admin.teachers.create') }}">Thêm giáo viên</a></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">Tích tên để gán vào lớp. Giáo viên được gán luôn được giao bài, chấm bài, xem bảng điểm của lớp; chỉ thấy lớp mình được gán. Người đầu tiên là giáo viên chính.</div>
            </div>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">Lưu</button>
            <a href="{{ $classroom->exists ? route('admin.classrooms.show', $classroom) : route('admin.classrooms.index') }}" class="btn btn-link">Huỷ</a>
        </div>
    </form>
@endsection
