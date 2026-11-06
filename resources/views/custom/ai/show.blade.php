@extends($layout)

@section('title', ($import->title ?: 'Dựng bài bằng AI').' — duyệt')

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <a href="{{ route($ai.'.create', [$course, 'lesson' => $import->lesson_id]) }}"><i class="ti ti-arrow-left me-1"></i>AI dựng bài tập — {{ $import->lesson?->title }}</a>
        <span class="badge {{ $import->status === 'failed' ? 'bg-red-lt' : ($import->status === 'review' ? 'bg-green-lt' : 'bg-blue-lt') }}" id="ai-label">{{ \App\Models\AiImport::STATUSES[$import->status] }}</span>
        <span class="small text-muted">{{ $import->source_name }} · {{ count($import->audio ?? []) }} file nghe</span>
        <form method="POST" action="{{ route($ai.'.destroy', [$course, $import]) }}" class="ms-auto" onsubmit="return confirm('Xoá yêu cầu này?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger">Xoá</button></form>
    </div>

    @if (in_array($import->status, ['uploading', ...\App\Models\AiImport::RUNNING], true))
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span><span id="ai-progress">{{ \App\Models\AiImport::STATUSES[$import->status] }}…</span></div>
            <div class="small text-muted mt-1">Để trang mở, hệ thống tự cập nhật. Tài liệu dài có thể mất vài phút.</div>
        </div></div>
    @elseif ($import->status === 'failed')
        <div class="alert alert-danger">{{ $import->error }}</div>
    @elseif ($import->status === 'saved')
        <div class="alert alert-success">Đã lưu thành bài tập. <a href="{{ route($prefix.'.edit', [$course, $import->custom_exercise_id]) }}">Mở bài tập</a></div>
    @endif

    @if ($import->status === 'review')
        @if ($missing)
            <div class="alert alert-warning">
                @foreach ($missing as $i)
                    <div><b>Phần {{ $i + 1 }}</b> ({{ $import->sections[$i]['title'] }}) cần file nghe nhưng chưa có{{ ! empty($import->sections[$i]['audio_file']) ? ' (tài liệu ghi: '.$import->sections[$i]['audio_file'].')' : '' }}.
                        Tải file lên, hoặc tạo giọng đọc bằng AI từ lời thoại trong tài liệu? <a href="#section-{{ $i }}">Xử lý</a></div>
                @endforeach
            </div>
        @endif
        @error('save')<div class="alert alert-danger">{{ $message }}</div>@enderror
        <div id="ai-flash"></div>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="text-muted small"><span id="ai-count">{{ count($import->sections) }}</span> phần · dùng ⬆ ⬇ để đổi thứ tự, 🗑 để bỏ bớt phần.</span>
            <div class="dropdown ms-auto">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="ti ti-refresh me-1"></i>Dựng lại toàn bộ</button>
                <div class="dropdown-menu dropdown-menu-end" style="min-width: 24rem">
                    <form method="POST" action="{{ route($ai.'.rebuild-all', [$course, $import]) }}" class="js-rebuild-all" onsubmit="return confirm('Dựng lại nội dung của TẤT CẢ các phần theo giáo trình? Những chỗ đã sửa tay trong từng phần sẽ bị thay.')">@csrf
                        <input type="hidden" name="style" value="strict">
                        <button class="dropdown-item d-block text-wrap"><b>Dựng lại tất cả theo giáo trình</b><div class="small text-muted">Giữ cách chia phần, thứ tự, file nghe; AI dựng lại nội dung từng phần (chép nguyên văn, dùng đáp án có sẵn, khớp lời file nghe).</div></button>
                    </form>
                    <form method="POST" action="{{ route($ai.'.rebuild-all', [$course, $import]) }}" class="js-rebuild-all" onsubmit="return confirm('AI sẽ tự viết lại TẤT CẢ các phần (câu hỏi, đáp án mới) theo mẫu giáo trình. Những chỗ đã sửa tay sẽ bị thay. Tiếp tục?')">@csrf
                        <input type="hidden" name="style" value="model">
                        <button class="dropdown-item d-block text-wrap"><b>Dựng lại tất cả, lấy giáo trình làm mẫu</b><div class="small text-muted">AI tự viết lại cùng dạng bài, chủ đề, số câu, độ khó — dùng khi dữ liệu gốc lỗi nhiều.</div></button>
                    </form>
                </div>
            </div>
        </div>

        @foreach ($import->sections as $i => $s)
            @php $t = \App\Support\QuestionTypes\Registry::find($s['type_code']); $audio = collect($import->audio ?? [])->firstWhere('id', $s['audio_id'] ?? null); @endphp
            <div class="card mb-3 ai-sec {{ $s['status'] === 'ok' ? '' : ($s['status'] === 'pending' ? 'border-primary' : 'border-danger') }}" id="section-{{ $i }}" data-section="{{ $i }}" data-status="{{ $s['status'] }}" data-audio="{{ $s['audio_id'] ?? '' }}" data-title="{{ $s['title'] }}">
                <div class="card-header">
                    <h3 class="card-title"><span class="badge {{ $s['status'] === 'ok' ? 'bg-green-lt' : ($s['status'] === 'pending' ? 'bg-blue-lt' : 'bg-red-lt') }} me-1">Phần <span data-sec-no>{{ $i + 1 }}</span></span>{{ $s['title'] }} <span class="small text-muted">· {{ $t?->name }}</span></h3>
                    <div class="card-actions d-flex align-items-center gap-1">
                        <form method="POST" action="{{ route($ai.'.section.move', [$course, $import, $i]) }}" class="d-inline js-sec-move">@csrf<input type="hidden" name="dir" value="up">
                            <button class="btn btn-sm btn-ghost-secondary px-2" title="Đưa phần này lên trên" data-sec-up @disabled($i === 0)><i class="ti ti-arrow-up"></i></button></form>
                        <form method="POST" action="{{ route($ai.'.section.move', [$course, $import, $i]) }}" class="d-inline js-sec-move">@csrf<input type="hidden" name="dir" value="down">
                            <button class="btn btn-sm btn-ghost-secondary px-2" title="Đưa phần này xuống dưới" data-sec-down @disabled($i === count($import->sections) - 1)><i class="ti ti-arrow-down"></i></button></form>
                        <form method="POST" action="{{ route($ai.'.section.destroy', [$course, $import, $i]) }}" class="d-inline js-sec-delete">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-ghost-danger px-2" title="Xoá phần này" data-sec-del @disabled(count($import->sections) <= 1)><i class="ti ti-trash"></i></button></form>
                        @if (($s['build_style'] ?? 'strict') === 'model')<span class="badge bg-purple-lt me-1" title="Lần dựng gần nhất: AI tự viết lại, lấy giáo trình làm mẫu">tự dựng theo mẫu</span>@endif
                        <div class="dropdown d-inline-block">
                            <button type="button" class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">↻ Dựng lại</button>
                            <div class="dropdown-menu dropdown-menu-end" style="min-width: 22rem">
                                <form method="POST" action="{{ route($ai.'.section.rebuild', [$course, $import, $i]) }}" class="js-rebuild" data-section="{{ $i }}">@csrf
                                    <input type="hidden" name="style" value="strict">
                                    <button class="dropdown-item d-block text-wrap"><b>Dựng theo giáo trình</b><div class="small text-muted">Chép nguyên văn đề, câu hỏi, lựa chọn; dùng đáp án có trong tài liệu.</div></button>
                                </form>
                                <form method="POST" action="{{ route($ai.'.section.rebuild', [$course, $import, $i]) }}" class="js-rebuild" data-section="{{ $i }}" onsubmit="return confirm('AI sẽ tự viết lại phần này (câu hỏi, đáp án mới) theo mẫu của giáo trình. Tiếp tục?')">@csrf
                                    <input type="hidden" name="style" value="model">
                                    <button class="dropdown-item d-block text-wrap"><b>Dựng lại, lấy giáo trình làm mẫu</b><div class="small text-muted">Khi dữ liệu gốc có thể lỗi (OCR sai, mất chữ, thiếu tranh, đáp án mơ hồ): AI tự viết lại cùng dạng bài, chủ đề, số câu, độ khó.</div></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row g-0 ai-sec-body">
                    <div class="col-lg-7 border-end">
                        <div class="card-body">
                            @if ($s['status'] === 'pending')
                                <div class="d-flex align-items-center gap-2 text-primary py-4 justify-content-center"><span class="spinner-border spinner-border-sm"></span>
                                    <span>AI đang dựng lại riêng phần này… các phần khác không bị ảnh hưởng.</span></div>
                            @else
                            @if (! empty($s['flags']))<div class="alert alert-warning py-2"><b>AI chưa chắc:</b><ul class="mb-0">@foreach ($s['flags'] as $f)<li>{{ $f }}</li>@endforeach</ul></div>@endif
                            @if (! empty($s['errors']))<div class="alert alert-danger py-2"><b>Sai lược đồ:</b><ul class="mb-0">@foreach (array_slice($s['errors'], 0, 8) as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
                            @if ($t && ! empty($s['content']) && empty($s['errors']))
                                @php
                                    $render = $s['content'];
                                    if ($audio) { $render['stimulus'] = ($render['stimulus'] ?? []); $render['stimulus']['audio'] = route($ai.'.audio-file', [$course, $import, $audio['id']]); }
                                    if (! empty($s['image_path'])) { $render['stimulus'] = ($render['stimulus'] ?? []); $render['stimulus']['image'] = route($ai.'.section.image-file', [$course, $import, $i]); }
                                @endphp
                                @include('question-types.render', ['type' => $t, 'content' => $render, 'uid' => 'ai-sec-'.$i, 'seed' => 'sec'.$i])
                            @endif
                            @endif
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card-body">
                            <div class="small fw-semibold mb-1">Đoạn tài liệu gốc</div>
                            <div class="small bg-light rounded p-2 mb-3" style="white-space: pre-wrap; max-height: 260px; overflow: auto">{{ $s['source'] }}</div>
                            <div class="small fw-semibold mb-1">Ảnh của phần</div>
                            @if (! empty($s['image_path']))
                                <img src="{{ route($ai.'.section.image-file', [$course, $import, $i]) }}" class="img-fluid rounded border mb-1" style="max-height: 180px" alt="">
                            @else
                                <div class="small text-muted mb-1">Không có (chỉ cần khi bài phải nhìn tranh / hình).</div>
                            @endif
                            <form method="POST" action="{{ route($ai.'.section.image', [$course, $import, $i]) }}" enctype="multipart/form-data" class="d-flex gap-1 mb-3">@csrf
                                <input type="file" name="image" class="form-control form-control-sm" accept="image/*"><button class="btn btn-sm btn-outline-primary">{{ empty($s['image_path']) ? 'Thêm' : 'Thay' }}</button>
                                @if (! empty($s['image_path']))<button name="remove" value="1" class="btn btn-sm btn-ghost-danger" formnovalidate>Gỡ</button>@endif
                            </form>
                            <div class="small fw-semibold mb-1">File nghe</div>
                            @if ($audio)
                                <audio controls class="w-100" src="{{ route($ai.'.audio-file', [$course, $import, $audio['id']]) }}"></audio>
                                <div class="small text-muted">{{ $audio['name'] }}</div>
                                @include('custom.ai.transcript', ['a' => $audio])
                            @elseif (! empty($s['needs_audio']))
                                <div class="text-warning small mb-1">Chưa có file nghe.</div>
                                <form method="POST" action="{{ route($ai.'.section.audio', [$course, $import, $i]) }}" enctype="multipart/form-data" class="d-flex gap-1 mb-1">@csrf
                                    <input type="file" name="audio" class="form-control form-control-sm" accept=".mp3,.m4a,.wav,.ogg,.aac" required><button class="btn btn-sm btn-outline-primary">Tải lên</button>
                                </form>
                                @if ($ttsReady && (! empty($s['transcript']) || ! empty($s['content']['stimulus']['transcript'])))
                                    <form method="POST" action="{{ route($ai.'.section.tts', [$course, $import, $i]) }}">@csrf<button class="btn btn-sm btn-outline-secondary"><i class="ti ti-volume me-1"></i>Tạo giọng đọc AI từ lời thoại</button></form>
                                @endif
                            @else
                                <div class="small text-muted">Không cần.</div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <details @if ($s['status'] !== 'ok') open @endif>
                        <summary class="fw-semibold">Sửa phần này</summary>
                        <form method="POST" action="{{ route($ai.'.section.update', [$course, $import, $i]) }}" class="mt-2">@csrf @method('PUT')
                            <div class="row g-2 mb-2">
                                <div class="col-md-5"><label class="form-label small">Tên phần</label><input name="title" class="form-control form-control-sm" value="{{ $s['title'] }}" required></div>
                                <div class="col-md-4"><label class="form-label small">Dạng bài</label><select name="type_code" class="form-select form-select-sm">@foreach ($types as $code => $tt)<option value="{{ $code }}" @selected($code === $s['type_code'])>{{ $tt->name }}</option>@endforeach</select></div>
                                <div class="col-md-3"><label class="form-label small">Điểm / câu</label><input type="number" step="0.01" name="points_per_question" class="form-control form-control-sm" value="{{ $s['points_per_question'] ?? '' }}" placeholder="mặc định"></div>
                                <div class="col-12"><label class="form-label small">File nghe</label><select name="audio_id" class="form-select form-select-sm"><option value="">— Không —</option>@foreach ($import->audio ?? [] as $a)<option value="{{ $a['id'] }}" @selected(($s['audio_id'] ?? null) === $a['id'])>{{ $a['name'] }}</option>@endforeach</select></div>
                            </div>
                            @if ($t)
                                @include('question-types.editor', ['type' => $t, 'json' => json_encode($s['content'] ?? new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)])
                                <div class="form-hint">Đổi "Dạng bài" ở trên thì bấm Lưu trước, form sẽ đổi theo dạng mới.</div>
                            @else
                                <textarea name="content" class="form-control font-monospace small" rows="12" spellcheck="false">{{ json_encode($s['content'] ?? new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</textarea>
                            @endif
                            <button class="btn btn-sm btn-primary mt-2">Lưu phần</button>
                        </form>
                    </details>
                </div>
            </div>
        @endforeach

        <form method="POST" action="{{ route($ai.'.save', [$course, $import]) }}" class="card" id="ai-save">@csrf
            <div class="card-body d-flex flex-wrap gap-2 align-items-end">
                <div class="flex-fill"><label class="form-label">Tên bài tập</label><input name="title" class="form-control" value="{{ $import->title }}" required></div>
                <button class="btn btn-success" @disabled(collect($import->sections)->contains(fn ($s) => $s['status'] !== 'ok'))><i class="ti ti-device-floppy me-1"></i>Lưu thành bài tập tuỳ chỉnh</button>
            </div>
            @if (collect($import->sections)->contains(fn ($s) => $s['status'] !== 'ok'))<div class="card-footer small text-danger">Còn phần sai lược đồ — sửa hoặc bấm "Dựng lại".</div>@endif
        </form>
    @endif
@endsection

@if (in_array($import->status, ['uploading', ...\App\Models\AiImport::RUNNING], true))
    @push('scripts')
    <script>
    (async function poll() {
        try {
            const r = await fetch(@json(route($ai.'.tick', [$course, $import])), { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' } });
            const j = await r.json();
            document.getElementById('ai-progress').textContent = j.label + (j.total ? ' (' + j.done + '/' + j.total + ' phần)' : '') + '…';
            if (!@json(['uploading', ...\App\Models\AiImport::RUNNING]).includes(j.status)) return location.reload();
        } catch (e) {}
        setTimeout(poll, 3000);
    })();
    </script>
    @endpush
@elseif ($import->status === 'review')
    @push('scripts')
    <script>
    // Màn duyệt: dựng lại / đổi thứ tự / xoá phần đều gửi ngầm, giao diện đổi bằng JS — không tải lại cả trang.
    (function () {
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const tickUrl = @json(route($ai.'.tick', [$course, $import]));
        const SPIN = '<div class="d-flex align-items-center gap-2 text-primary py-4 justify-content-center"><span class="spinner-border spinner-border-sm"></span><span>AI đang dựng lại riêng phần này… các phần khác không bị ảnh hưởng.</span></div>';
        let timer = null;

        const cards = () => [...document.querySelectorAll('.ai-sec')];
        const pendingCards = () => cards().filter((c) => c.dataset.status === 'pending');
        const pendingAudio = () => [...document.querySelectorAll('[data-transcript-status="pending"]')];

        function flash(msg, type) {
            const box = document.getElementById('ai-flash');
            if (!box) return;
            box.innerHTML = '';
            const div = document.createElement('div');
            div.className = 'alert alert-' + (type || 'success') + ' py-2';
            div.textContent = msg;
            box.append(div);
            clearTimeout(flash._t);
            flash._t = setTimeout(() => div.remove(), 4000);
        }
        async function send(form, extra) {
            const r = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            const j = await r.json().catch(() => ({}));
            if (!r.ok || j.ok === false) throw new Error(j.message || 'Lỗi ' + r.status);
            return j;
        }
        function closeDropdown(el) {
            const toggle = el.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]');
            if (toggle && window.bootstrap) bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        }

        // ---- nạp lại một số thẻ từ máy chủ (sau khi dựng lại / chép lời xong)
        async function freshDoc() {
            const r = await fetch(location.pathname + location.search, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
            return new DOMParser().parseFromString(await r.text(), 'text/html');
        }
        function swap(oldEl, newEl) {
            if (!oldEl || !newEl) return;
            oldEl.replaceWith(newEl);
            if (window.QT) QT.init(newEl);
            if (window.QTE) newEl.querySelectorAll('.qte').forEach((x) => QTE.mount(x));
        }
        async function refresh(indexes) {
            const doc = await freshDoc();
            indexes.forEach((i) => swap(document.getElementById('section-' + i), doc.getElementById('section-' + i)));
            swap(document.getElementById('ai-save'), doc.getElementById('ai-save'));
        }

        // ---- đánh số lại sau khi đổi thứ tự / xoá: id, số "Phần N", URL /phan/N/… trong form, ảnh
        function renumber() {
            const all = cards();
            all.forEach((card, i) => {
                const old = card.dataset.section;
                card.id = 'section-' + i;
                card.dataset.section = i;
                card.querySelectorAll('[data-sec-no]').forEach((x) => x.textContent = i + 1);
                card.querySelectorAll('[data-section]').forEach((x) => x.dataset.section = i);
                const fix = (v) => v.replace(new RegExp('/phan/' + old + '(?=/|$|\\?)'), '/phan/' + i);
                card.querySelectorAll('form[action]').forEach((f) => f.setAttribute('action', fix(f.getAttribute('action'))));
                card.querySelectorAll('img[src], [style*="/phan/"]').forEach((x) => { if (x.getAttribute('src')) x.setAttribute('src', fix(x.getAttribute('src'))); });
                const up = card.querySelector('[data-sec-up]'), down = card.querySelector('[data-sec-down]'), del = card.querySelector('[data-sec-del]');
                if (up) up.disabled = i === 0;
                if (down) down.disabled = i === all.length - 1;
                if (del) del.disabled = all.length <= 1;
            });
            const n = document.getElementById('ai-count');
            if (n) n.textContent = all.length;
        }

        // Gửi lần lượt (không song song) để máy chủ nhận đúng thứ tự thao tác.
        let queue = Promise.resolve();
        const enqueue = (fn) => (queue = queue.then(fn, fn));

        function blockedByPending() {
            if (pendingCards().length) { flash('Đang có phần dựng lại — chờ xong rồi hãy đổi thứ tự / xoá phần.', 'warning'); return true; }
            return false;
        }

        function markPending(card) {
            card.dataset.status = 'pending';
            card.classList.remove('border-danger');
            card.classList.add('border-primary');
            const left = card.querySelector('.ai-sec-body .card-body');
            if (left) left.innerHTML = SPIN;
            card.querySelectorAll('.card-footer details').forEach((d) => d.open = false);
            const save = document.querySelector('#ai-save button.btn-success');
            if (save) save.disabled = true;
        }

        document.addEventListener('submit', async (e) => {
            const form = e.target;
            if (e.defaultPrevented) return;

            // Đổi thứ tự: đổi chỗ thẻ ngay, gửi ngầm; lỗi thì đổi lại.
            if (form.matches('form.js-sec-move')) {
                e.preventDefault();
                if (blockedByPending()) return;
                const card = form.closest('.ai-sec');
                const up = form.querySelector('[name=dir]').value === 'up';
                const other = up ? card.previousElementSibling : card.nextElementSibling;
                if (!other || !other.classList.contains('ai-sec')) return;
                const action = form.getAttribute('action'), body = new FormData(form);
                up ? other.before(card) : other.after(card);
                renumber();
                card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                card.classList.add('shadow');
                setTimeout(() => card.classList.remove('shadow'), 600);
                enqueue(async () => {
                    try {
                        const r = await fetch(action, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
                        const j = await r.json().catch(() => ({}));
                        if (!r.ok || j.ok === false) throw new Error(j.message || 'Lỗi ' + r.status);
                    } catch (err) {
                        alert('Chưa đổi được thứ tự: ' + err.message + '\nTrang sẽ tải lại để khớp dữ liệu.');
                        location.reload();
                    }
                });
                return;
            }

            // Xoá phần: gỡ thẻ ngay, gửi ngầm.
            if (form.matches('form.js-sec-delete')) {
                e.preventDefault();
                if (blockedByPending()) return;
                const card = form.closest('.ai-sec');
                if (cards().length <= 1) return;
                if (!confirm('Xoá phần ' + (+card.dataset.section + 1) + ' — ' + card.dataset.title + ' khỏi bài?')) return;
                const action = form.getAttribute('action'), body = new FormData(form);
                card.remove();
                renumber();
                enqueue(async () => {
                    try {
                        const r = await fetch(action, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
                        const j = await r.json().catch(() => ({}));
                        if (!r.ok || j.ok === false) throw new Error(j.message || 'Lỗi ' + r.status);
                        flash(j.message);
                        // Nút "Lưu" có thể đổi trạng thái (phần lỗi vừa bị bỏ).
                        const doc = await freshDoc();
                        swap(document.getElementById('ai-save'), doc.getElementById('ai-save'));
                    } catch (err) {
                        alert('Chưa xoá được: ' + err.message + '\nTrang sẽ tải lại để khớp dữ liệu.');
                        location.reload();
                    }
                });
                return;
            }

            // Dựng lại một phần: chỉ thẻ đó chuyển sang "đang dựng".
            if (form.matches('form.js-rebuild')) {
                e.preventDefault();
                closeDropdown(form);
                const card = document.getElementById('section-' + form.dataset.section);
                try { await enqueue(() => send(form)); } catch (err) { return alert('Chưa dựng lại được: ' + err.message); }
                markPending(card);
                schedule();
                return;
            }

            // Dựng lại toàn bộ: mọi thẻ chuyển sang "đang dựng", xong phần nào hiện phần đó.
            if (form.matches('form.js-rebuild-all')) {
                e.preventDefault();
                closeDropdown(form);
                let j;
                try { j = await enqueue(() => send(form)); } catch (err) { return alert('Chưa dựng lại được: ' + err.message); }
                (j.sections || []).forEach((i) => { const c = document.getElementById('section-' + i); if (c) markPending(c); });
                flash(j.message, 'info');
                schedule();
            }
        });

        async function tick() {
            timer = null;
            try {
                const r = await fetch(tickUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
                const j = await r.json();
                if (j.status !== 'review') return location.reload();
                const done = pendingCards().map((c) => +c.dataset.section).filter((i) => j.sections[i] && j.sections[i] !== 'pending');
                // File nghe vừa chép lời xong → nạp lại các phần dùng file đó.
                pendingAudio().forEach((el) => {
                    if (j.audio[el.dataset.transcript] !== 'pending') {
                        document.querySelectorAll('.ai-sec[data-audio="' + el.dataset.transcript + '"]').forEach((c) => done.push(+c.dataset.section));
                    }
                });
                if (done.length) await refresh([...new Set(done)]);
            } catch (e) {}
            schedule();
        }
        function schedule() {
            if (!timer && (pendingCards().length || pendingAudio().length)) timer = setTimeout(tick, 3000);
        }

        schedule();
    })();
    </script>
    @endpush
@endif
