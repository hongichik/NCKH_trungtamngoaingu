@extends('layouts.teacher')

@section('title', 'Giao bài')
@section('pretitle', $classroom->name)

@section('content')
    <form method="POST" action="{{ route('teacher.assignments.store', $classroom) }}" class="card" style="max-width: 900px">@csrf
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label required">Bài (Unit)</label>
                <select name="unit_id" class="form-select" id="unit-select" required>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}" data-skills='@json(array_column($u->published_sections, 'skill'))' @disabled(! $u->published_sections)>
                            {{ $u->title }} — {{ $u->published_sections ? collect($u->published_sections)->map(fn ($s) => \App\Models\ExerciseSet::SKILLS[$s['skill']])->implode(', ') : 'chưa có bộ bài tập xuất bản' }}
                        </option>
                    @endforeach
                </select>
                @if ($units->filter(fn ($u) => $u->published_sections)->isEmpty())<div class="form-hint text-danger">Chưa có Unit nào có bộ bài tập đã xuất bản. Vào “Tạo bài tập”, sinh và xuất bản trước.</div>@endif
            </div>
            <div class="mb-3">
                <label class="form-label required">Kỹ năng</label>
                <div class="d-flex flex-wrap gap-3" id="skills">
                    @foreach (\App\Models\ExerciseSet::SKILLS as $k => $label)
                        <label class="form-check form-check-inline mb-0"><input class="form-check-input" type="checkbox" name="skills[]" value="{{ $k }}" checked><span class="form-check-label">{{ $label }}</span></label>
                    @endforeach
                </div>
                <div class="form-hint">Học viên làm các kỹ năng đã chọn trên 1 trang (chuyển tab tự do), nộp 1 lần.</div>
            </div>
            <div class="mb-3"><label class="form-label">Tên bài giao</label><input name="title" class="form-control" placeholder="Để trống = tên Unit + kỹ năng"></div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Chế độ</label>
                    <select name="mode" class="form-select" id="mode">
                        <option value="practice">Luyện tập — làm nhiều lần, xem đáp án ngay</option>
                        <option value="test">Kiểm tra — có giờ, giới hạn lượt</option>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Mở bài lúc</label><input type="datetime-local" name="opens_at" class="form-control"><div class="form-hint">Trống = mở ngay</div></div>
                <div class="col-md-4"><label class="form-label">Hạn nộp</label><input type="datetime-local" name="due_at" class="form-control"><div class="form-hint">Trống = không hạn</div></div>
                <div class="col-md-3"><label class="form-label">Số lượt làm tối đa</label><input type="number" name="max_attempts" class="form-control" min="1" max="50" placeholder="Không giới hạn"></div>
                <div class="col-md-3"><label class="form-label">Thời gian làm (phút)</label><input type="number" name="time_limit_minutes" class="form-control" min="1" max="600" placeholder="Không giới hạn"></div>
                <div class="col-md-3"><label class="form-label">Số lần nghe tối đa</label><input type="number" name="max_listens" class="form-control" min="1" max="10" placeholder="Không giới hạn"></div>
                <div class="col-md-3">
                    <label class="form-label">Hiện đáp án</label>
                    <select name="show_answers" class="form-select" id="show-answers">
                        <option value="after_submit">Ngay sau khi nộp</option>
                        <option value="after_due">Sau hạn nộp</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex">
            <a href="{{ route('teacher.classrooms.show', $classroom) }}" class="btn btn-link">Huỷ</a>
            <button class="btn btn-primary ms-auto"><i class="ti ti-send me-1"></i>Giao bài</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const sel = document.getElementById('unit-select');
    const sync = () => {
        const opt = sel.selectedOptions[0];
        const ok = opt ? JSON.parse(opt.dataset.skills || '[]') : [];
        document.querySelectorAll('#skills input').forEach((c) => { c.disabled = !ok.includes(c.value); c.checked = ok.includes(c.value); });
    };
    sel.addEventListener('change', sync);
    sync();
    // Chế độ kiểm tra: gợi ý 1 lượt, đáp án sau hạn nộp
    document.getElementById('mode').addEventListener('change', (e) => {
        const test = e.target.value === 'test';
        document.querySelector('[name=max_attempts]').placeholder = test ? '1 (mặc định)' : 'Không giới hạn';
        document.getElementById('show-answers').value = test ? 'after_due' : 'after_submit';
    });
})();
</script>
@endpush
