@extends($layout)

@section('title', $exercise->title)

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        @php $lessonsPrefix = str_replace('custom', 'lessons', $prefix); $canLesson = $adminSide || auth()->user()->canEditCourse($course); @endphp
        @if ($canLesson && $exercise->lesson)
            <a href="{{ route($lessonsPrefix.'.edit', [$course, $exercise->lesson]) }}"><i class="ti ti-arrow-left me-1"></i>Bài học “{{ $exercise->lesson->title }}”</a>
        @elseif ($exercise->sourceClassroom)
            <a href="{{ route('teacher.classrooms.show', $exercise->sourceClassroom) }}"><i class="ti ti-arrow-left me-1"></i>Lớp {{ $exercise->sourceClassroom->name }}</a>
        @endif
        @if ($exercise->sourceClassroom)<span class="badge bg-orange-lt">Bài riêng của lớp {{ $exercise->sourceClassroom->name }}</span>@endif
        <span class="badge {{ ['draft' => 'bg-secondary-lt', 'published' => 'bg-green-lt', 'hidden' => 'bg-red-lt'][$exercise->status] }}">{{ \App\Models\CustomExercise::STATUSES[$exercise->status] }}</span>
        <span class="text-muted small">Tạo bởi {{ $exercise->created_by_name }} · {{ $exercise->created_at->format('d/m/Y') }}@if ($exercise->parent) · nhân bản từ “{{ $exercise->parent->title }}”@endif</span>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route($prefix.'.preview', [$course, $exercise]) }}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="ti ti-eye me-1"></i>Xem như học viên</a>
            <form method="POST" action="{{ route($prefix.'.duplicate', [$course, $exercise]) }}">@csrf<button class="btn btn-outline-secondary btn-sm"><i class="ti ti-copy me-1"></i>Nhân bản</button></form>
        </div>
    </div>

    @if ($canManage && $attemptCount)
        {{-- Bài đã có người làm vẫn sửa được: điểm các lượt cũ giữ nguyên; muốn áp đáp án mới cho lượt cũ thì bấm "Chấm lại". --}}
        <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
            <i class="ti ti-info-circle fs-3"></i>
            <div class="flex-fill">Đã có <b>{{ $attemptCount }}</b> bài làm được nộp. Bạn vẫn sửa bài bình thường: <b>điểm các bài đã nộp giữ nguyên</b>, lượt làm mới dùng nội dung mới.
                <div class="small text-muted">Sửa đáp án sai? Bấm “Chấm lại bài đã nộp” để tính lại điểm theo đáp án mới (phần Viết / Nói đã chấm giữ nguyên, phần mới thêm không tính cho lượt cũ).</div></div>
            <form method="POST" action="{{ route($prefix.'.regrade', [$course, $exercise]) }}" onsubmit="return confirm('Chấm lại {{ $attemptCount }} bài đã nộp theo đáp án hiện tại?')">@csrf
                <button class="btn btn-outline-primary btn-sm"><i class="ti ti-refresh me-1"></i>Chấm lại bài đã nộp</button></form>
        </div>
    @elseif (! $canManage)
        <div class="alert alert-info">Bài của {{ $exercise->created_by_name }}. Bạn xem và thêm vào lớp được; muốn sửa thì <b>Nhân bản</b>.</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            @forelse ($exercise->items as $item)
                @php $t = $item->type(); $editable = $canManage; $aiState = $item->meta['ai'] ?? null; $aiPending = ($aiState['status'] ?? null) === 'pending'; @endphp
                <div class="card mb-3 {{ $aiPending ? 'border-primary' : '' }}" id="item-{{ $item->id }}" data-item="{{ $item->id }}" data-ai="{{ $aiState['status'] ?? '' }}">
                    <div class="card-header">
                        <h3 class="card-title"><span class="badge bg-blue-lt me-1">Phần {{ $loop->iteration }}</span>{{ $item->title ?: $t?->name }}</h3>
                        <div class="card-actions d-flex gap-1">
                            <span class="small text-muted me-2">{{ $t?->name ?? $item->type_code }}</span>
                            @if (! empty($item->meta['audio_ai']))<span class="badge bg-purple-lt me-1" title="File nghe của phần do AI đọc">giọng AI</span>@endif
                            @if ($canManage && $t)
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" @disabled($aiPending)><i class="ti ti-sparkles me-1"></i>AI</button>
                                    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 24rem">
                                        @if (true)
                                            <form method="POST" action="{{ route($prefix.'.items.ai', [$course, $exercise, $item]) }}" class="js-item-ai mb-3">@csrf
                                                <input type="hidden" name="action" value="rebuild">
                                                <div class="fw-semibold mb-1">Dựng lại phần này bằng AI</div>
                                                <textarea name="note" class="form-control form-control-sm mb-2" rows="2" maxlength="1000" placeholder="Yêu cầu thêm (tuỳ chọn), vd: thêm 2 câu, câu hỏi khó hơn, đáp án theo bài nghe…"></textarea>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button name="style" value="strict" class="btn btn-sm btn-primary" title="Chép nguyên văn đề trong tài liệu gốc, dùng đáp án có sẵn, khớp lời file nghe">Theo giáo trình</button>
                                                    <button name="style" value="model" class="btn btn-sm btn-outline-primary" title="AI tự viết lại cùng dạng, chủ đề, số câu, độ khó">Lấy giáo trình làm mẫu</button>
                                                </div>
                                                <div class="form-hint">Nội dung hiện tại của phần sẽ được thay. Có file nghe thì AI chép lời file để câu hỏi khớp bài nghe.</div>
                                            </form>
                                        @endif
                                        <div class="fw-semibold mb-1">Giọng đọc AI cho file nghe</div>
                                        @php $speech = \App\Support\CustomExercise\ItemAiService::speechText($item); @endphp
                                        @if ($speech !== '')
                                            <form method="POST" action="{{ route($prefix.'.items.ai', [$course, $exercise, $item]) }}" class="js-item-ai" @if ($item->audio_path && empty($item->meta['audio_ai'])) data-confirm="Thay file nghe hiện tại bằng giọng đọc AI? File gốc được giữ lại, đổi lại được bất cứ lúc nào." @endif>@csrf
                                                <input type="hidden" name="action" value="tts">
                                                <button class="btn btn-sm btn-outline-secondary"><i class="ti ti-volume me-1"></i>{{ ! empty($item->meta['audio_ai']) ? 'Tạo lại giọng đọc AI' : 'Tạo giọng đọc AI từ lời bài nghe' }}</button>
                                            </form>
                                            <div class="form-hint">Đọc từ “Lời bài nghe”{{ $t->engine === 'dictation' && empty($item->content['stimulus']['transcript']) ? ' (bài chép chính tả: đọc lần lượt từng câu)' : '' }} ({{ mb_strlen($speech) }} ký tự).</div>
                                        @else
                                            <div class="small text-muted">Chưa có lời bài nghe — thêm “Lời bài nghe” trong mục Sửa nội dung.</div>
                                        @endif
                                        @if (! empty($item->meta['audio_ai']))
                                            <form method="POST" action="{{ route($prefix.'.items.ai', [$course, $exercise, $item]) }}" class="js-item-ai mt-2">@csrf
                                                <input type="hidden" name="action" value="revert">
                                                <button class="btn btn-sm btn-ghost-secondary"><i class="ti ti-arrow-back-up me-1"></i>{{ ! empty($item->meta['audio_original']) ? 'Dùng lại file nghe gốc' : 'Bỏ giọng đọc AI' }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            @if ($editable)
                                <form method="POST" action="{{ route($prefix.'.items.move', [$course, $exercise, $item]) }}">@csrf<input type="hidden" name="dir" value="up"><button class="btn btn-sm btn-ghost-secondary" @disabled($loop->first)>▲</button></form>
                                <form method="POST" action="{{ route($prefix.'.items.move', [$course, $exercise, $item]) }}">@csrf<input type="hidden" name="dir" value="down"><button class="btn btn-sm btn-ghost-secondary" @disabled($loop->last)>▼</button></form>
                                <form method="POST" action="{{ route($prefix.'.items.destroy', [$course, $exercise, $item]) }}" onsubmit="return confirm(@js($attemptCount ? 'Xoá phần này? Điểm các bài đã nộp vẫn giữ nguyên (phần này vẫn hiện trong kết quả cũ).' : 'Xoá phần này?'))">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger"><i class="ti ti-trash"></i></button></form>
                            @endif
                        </div>
                    </div>
                    <div class="card-body item-body">
                        @if ($aiPending)
                            <div class="d-flex align-items-center gap-2 text-primary py-4 justify-content-center"><span class="spinner-border spinner-border-sm"></span>
                                <span>{{ ($aiState['action'] ?? '') === 'tts' ? 'AI đang tạo giọng đọc cho phần này…' : 'AI đang dựng lại riêng phần này…' }}</span></div>
                        @else
                        @if (($aiState['status'] ?? null) === 'error')<div class="alert alert-danger py-2">{{ $aiState['error'] }}</div>@endif
                        @if (! empty($item->meta['flags']))
                            <div class="alert alert-warning py-2"><b>AI chưa chắc:</b><ul class="mb-0">@foreach ($item->meta['flags'] as $f)<li>{{ $f }}</li>@endforeach</ul></div>
                        @endif
                        @error('item_'.$item->id)<div class="alert alert-danger py-2">@foreach ($errors->get('item_'.$item->id) as $m)<div>{{ is_array($m) ? implode(' · ', $m) : $m }}</div>@endforeach</div>@enderror
                        @if ($t)
                            @include('question-types.render', ['type' => $t, 'content' => $item->contentWithAudio($item->audio_path ? route($prefix.'.items.audio-file', [$course, $exercise, $item]) : null, $item->image_path ? route($prefix.'.items.image-file', [$course, $exercise, $item]) : null), 'uid' => 'qt-item-'.$item->id, 'seed' => 'item'.$item->id])
                        @else
                            <div class="text-danger">Dạng bài "{{ $item->type_code }}" không còn trong thư viện.</div>
                        @endif
                        @endif
                    </div>
                    @if ($editable)
                        <div class="card-footer">
                            <details @if ($errors->has('item_'.$item->id)) open @endif>
                                <summary class="fw-semibold"><i class="ti ti-edit me-1"></i>Sửa nội dung & cách tính điểm</summary>
                                <form method="POST" action="{{ route($prefix.'.items.update', [$course, $exercise, $item]) }}" class="mt-2">@csrf @method('PUT')
                                    <div class="mb-2"><label class="form-label">Tên phần</label><input name="title" class="form-control" value="{{ $item->title }}" placeholder="{{ $t?->name }}"></div>
                                    @include('question-types.editor', ['type' => $t, 'json' => $errors->has('item_'.$item->id) ? old('content') : json_encode($item->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)])
                                    <div class="row g-2 mt-1">
                                        <div class="col-6 col-md-3"><label class="form-label small">Điểm / ý</label><input type="number" step="0.05" name="points" class="form-control form-control-sm" value="{{ $item->scoring['points'] ?? '' }}" placeholder="{{ $t ? \App\Support\QuestionTypes\Registry::scoring($t)['points'] : 1 }}"></div>
                                        <div class="col-6 col-md-3"><label class="form-label small">Trừ / ý sai</label><input type="number" step="0.05" name="penalty" class="form-control form-control-sm" value="{{ $item->scoring['penalty'] ?? '' }}" placeholder="0"></div>
                                        <div class="col-6 col-md-3"><label class="form-label small">Điểm từng phần</label><select name="partial" class="form-select form-select-sm"><option value="">Mặc định</option><option value="1" @selected(($item->scoring['partial'] ?? null) === true)>Có</option><option value="0" @selected(($item->scoring['partial'] ?? null) === false)>Không</option></select></div>
                                        <div class="col-6 col-md-3"><label class="form-label small">Quy đổi</label><select name="scale" class="form-select form-select-sm"><option value="">Mặc định</option>@foreach ($scales as $k => $l)<option value="{{ $k }}" @selected(($item->scoring['scale'] ?? null) === $k)>{{ $l }}</option>@endforeach</select></div>
                                    </div>
                                    <button class="btn btn-primary btn-sm mt-2">Lưu phần</button>
                                </form>
                                <form method="POST" action="{{ route($prefix.'.items.audio', [$course, $exercise, $item]) }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center mt-3">@csrf
                                    <span class="small fw-semibold">File nghe của phần:</span>
                                    @if ($item->audio_path)<span class="small text-success">✓ đã gắn</span><button name="remove" value="1" class="btn btn-sm btn-ghost-danger">Gỡ</button>@endif
                                    <input type="file" name="audio" class="form-control form-control-sm w-auto" accept=".mp3,.m4a,.wav,.ogg,.aac">
                                    <button class="btn btn-sm btn-outline-primary">Tải lên</button>
                                </form>
                                <form method="POST" action="{{ route($prefix.'.items.image', [$course, $exercise, $item]) }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center mt-2">@csrf
                                    <span class="small fw-semibold">Ảnh của phần (tranh, bản đồ…):</span>
                                    @if ($item->image_path)<span class="small text-success">✓ đã gắn</span><button name="remove" value="1" class="btn btn-sm btn-ghost-danger">Gỡ</button>@endif
                                    <input type="file" name="image" class="form-control form-control-sm w-auto" accept="image/*">
                                    <button class="btn btn-sm btn-outline-primary">Tải lên</button>
                                </form>
                            </details>
                        </div>
                    @endif
                </div>
            @empty
                <div class="card mb-3"><div class="card-body text-muted">Chưa có phần nào. Thêm phần ở bên phải.</div></div>
            @endforelse
        </div>

        <div class="col-lg-4">
            @if ($canManage)
                <form method="POST" action="{{ route($prefix.'.items.store', [$course, $exercise]) }}" class="card mb-3">@csrf
                    <div class="card-header"><h3 class="card-title">Thêm phần</h3></div>
                    <div class="card-body">
                        <label class="form-label">Dạng bài</label>
                        <select name="type_code" class="form-select mb-2" required>
                            @foreach ($types->groupBy('standard') as $std => $group)
                                <optgroup label="{{ \App\Support\QuestionTypes\QuestionType::STANDARDS[$std] }}">@foreach ($group as $t)<option value="{{ $t->code }}">{{ $t->name }}</option>@endforeach</optgroup>
                            @endforeach
                        </select>
                        <input name="title" class="form-control" placeholder="Tên phần (tuỳ chọn), vd Part 1">
                        <div class="form-hint">Phần mới có sẵn nội dung mẫu của dạng bài để sửa theo.</div>
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary">Thêm phần</button></div>
                </form>
            @endif

            <form method="POST" action="{{ route($prefix.'.update', [$course, $exercise]) }}" class="card mb-3">@csrf @method('PUT')
                <fieldset @disabled(! $canManage)>
                    <div class="card-header"><h3 class="card-title">Thông tin bài</h3></div>
                    <div class="card-body">
                        <div class="mb-2"><label class="form-label">Tên bài</label><input name="title" class="form-control" value="{{ $exercise->title }}" required></div>
                        <div class="mb-2"><label class="form-label">Kỹ năng</label><select name="skill" class="form-select">@foreach (\App\Models\CustomExercise::SKILLS as $k => $l)<option value="{{ $k }}" @selected($exercise->skill === $k)>{{ $l }}</option>@endforeach</select></div>
                        <div class="mb-2"><label class="form-label">Thuộc bài học</label><select name="lesson_id" class="form-select" required>@foreach ($lessons as $l)<option value="{{ $l->id }}" @selected($exercise->lesson_id === $l->id)>{{ $l->title }}</option>@endforeach</select></div>
                        <div class="mb-2"><label class="form-label">Giới hạn thời gian mặc định (phút)</label><input type="number" name="time_limit_minutes" class="form-control" value="{{ $exercise->time_limit_minutes }}"></div>
                        <div class="mb-2"><label class="form-label">Quy đổi điểm cả bài</label><select name="scale" class="form-select"><option value="">Theo từng dạng bài (thang 10)</option>@foreach ($scales as $k => $l)<option value="{{ $k }}" @selected(($exercise->scoring['scale'] ?? null) === $k)>{{ $l }}</option>@endforeach</select></div>
                        <div class="mb-2"><label class="form-label">Mô tả / hướng dẫn</label><textarea name="description" class="form-control" rows="3">{{ $exercise->description }}</textarea></div>
                        <div class="mb-2"><label class="form-label">Trạng thái</label><select name="status" class="form-select">@foreach (\App\Models\CustomExercise::STATUSES as $k => $l)<option value="{{ $k }}" @selected($exercise->status === $k)>{{ $l }}</option>@endforeach</select><div class="form-hint">"Dùng được": học viên các lớp của khoá thấy bài trong bài học{{ $exercise->sourceClassroom ? ' (bài riêng: chỉ lớp '.$exercise->sourceClassroom->name.')' : '' }}.</div></div>
                        @if ($adminSide)<label class="form-check"><input type="checkbox" class="form-check-input" name="is_standard" value="1" @checked($exercise->is_standard)><span class="form-check-label">Bài chuẩn của khoá</span></label>@endif
                    </div>
                    <div class="card-footer d-flex"><button class="btn btn-primary">Lưu</button></div>
                </fieldset>
            </form>
            @if ($canManage)
                <form method="POST" action="{{ route($prefix.'.destroy', [$course, $exercise]) }}" class="text-end" onsubmit="return confirm('{{ $locked ? 'Ẩn bài khỏi thư viện?' : 'Xoá bài tập?' }}')">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">{{ $locked ? 'Ẩn khỏi thư viện' : 'Xoá bài' }}</button>
                </form>
            @endif
        </div>
    </div>
@endsection

@if ($canManage)
    @push('scripts')
    <script>
    // AI cho từng phần: gửi ngầm, chỉ thẻ của phần đó hiện "AI đang làm" rồi tự nạp lại khi xong (không tải lại cả trang).
    (function () {
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const statusUrl = @json(route($prefix.'.items.ai-status', [$course, $exercise]));
        let timer = null;
        const pending = () => [...document.querySelectorAll('[data-item][data-ai="pending"]')];

        function spin(card, text) {
            card.dataset.ai = 'pending';
            card.classList.add('border-primary');
            card.querySelector('.item-body').innerHTML = '<div class="d-flex align-items-center gap-2 text-primary py-4 justify-content-center"><span class="spinner-border spinner-border-sm"></span><span>' + text + '</span></div>';
            card.querySelectorAll('.card-footer details').forEach((d) => d.open = false);
            const btn = card.querySelector('.card-actions [data-bs-toggle="dropdown"]');
            if (btn) btn.disabled = true;
        }
        async function refresh(ids) {
            const r = await fetch(location.pathname + location.search, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
            const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
            ids.forEach((id) => {
                const oldEl = document.getElementById('item-' + id), newEl = doc.getElementById('item-' + id);
                if (!oldEl || !newEl) return;
                oldEl.replaceWith(newEl);
                if (window.QT) QT.init(newEl);
                if (window.QTE) newEl.querySelectorAll('.qte').forEach((x) => QTE.mount(x));
            });
        }
        async function tick() {
            timer = null;
            try {
                const r = await fetch(statusUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
                const j = await r.json();
                const done = pending().map((c) => c.dataset.item).filter((id) => !j.items[id] || j.items[id].status !== 'pending');
                if (done.length) await refresh(done);
            } catch (e) {}
            schedule();
        }
        function schedule() { if (!timer && pending().length) timer = setTimeout(tick, 3000); }

        document.addEventListener('submit', async (e) => {
            const form = e.target.closest('form.js-item-ai');
            if (!form || e.defaultPrevented) return;
            e.preventDefault();
            if (form.dataset.confirm && !confirm(form.dataset.confirm)) return;
            const card = form.closest('[data-item]');
            const body = new FormData(form);
            if (e.submitter && e.submitter.name) body.set(e.submitter.name, e.submitter.value);
            const toggle = card.querySelector('.card-actions [data-bs-toggle="dropdown"]');
            if (toggle && window.bootstrap) bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
            let j;
            try {
                const r = await fetch(form.action, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
                j = await r.json().catch(() => ({}));
                if (!r.ok || j.ok === false) throw new Error(j.message || 'Lỗi ' + r.status);
            } catch (err) {
                return alert(err.message);
            }
            if (j.pending) {
                spin(card, j.message);
                schedule();
            } else {
                await refresh([card.dataset.item]);
            }
        });
        schedule();
    })();
    </script>
    @endpush
@endif
