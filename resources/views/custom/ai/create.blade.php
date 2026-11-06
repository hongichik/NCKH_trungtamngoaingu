@extends($layout)

@section('title', 'AI dựng bài tập — '.$lesson->title)

@section('content')
    <div class="mb-3">@if ($classrooms->isEmpty())<a href="{{ route(str_replace('custom', 'lessons', $prefix).'.edit', [$course, $lesson]) }}"><i class="ti ti-arrow-left me-1"></i>Bài học “{{ $lesson->title }}”</a>@else<a href="{{ route('teacher.classrooms.index') }}"><i class="ti ti-arrow-left me-1"></i>Lớp của tôi</a>@endif</div>
    @unless ($aiReady)<div class="alert alert-warning">Chưa cấu hình AI. Chủ hệ thống cần nhập khoá AI ở Quản trị hệ thống › AI.</div>@endunless
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-sparkles me-1 text-purple"></i>Tải tài liệu giáo viên đã soạn</h3></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Tên bài (để trống: AI tự đặt)</label><input id="ai-title" class="form-control" maxlength="200"></div>
                    <div class="alert alert-info py-2">Bài tập sẽ nằm trong bài học <b>{{ $lesson->title }}</b> (khoá {{ $course->title }}).</div>
                    @if ($classrooms->isNotEmpty())
                        <div class="mb-3"><label class="form-label required">Bài tập riêng cho lớp</label><select id="ai-class" class="form-select" required>@foreach ($classrooms as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label required">Tài liệu: Word (.docx), PDF, hoặc ảnh chụp các trang</label>
                        <input type="file" id="ai-doc" class="form-control" accept=".docx,.pdf,.txt,.jpg,.jpeg,.png,.webp" multiple>
                        <div class="form-hint">PDF được đọc ngay trên máy bạn; trang scan / ảnh chụp sẽ được AI đọc (OCR). <a href="{{ route($ai.'.template', $course) }}">Tải file Word mẫu</a> để biết cách soạn cho AI đọc chính xác nhất.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Các file nghe (mp3, m4a…) — có thể chọn nhiều</label>
                        <input type="file" id="ai-audio" class="form-control" accept=".mp3,.m4a,.wav,.ogg,.aac" multiple>
                        <div class="form-hint">Tài liệu ghi "nghe file track05.mp3" thì hệ thống tự gắn đúng file (không phân biệt hoa thường, dấu, đuôi file). Phần thiếu file sẽ được hỏi lại.</div>
                    </div>
                    <div class="progress mb-2 d-none" id="ai-prog"><div class="progress-bar" style="width: 0%"></div></div>
                    <div class="small" id="ai-status"></div>
                </div>
                <div class="card-footer text-end"><button type="button" class="btn btn-primary" id="ai-go" @disabled(! $aiReady)><i class="ti ti-wand me-1"></i>Gửi cho AI dựng bài</button></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-body small">
                    <div class="fw-semibold mb-1">AI làm gì?</div>
                    <ol class="mb-0 ps-3">
                        <li>Chia tài liệu thành các phần, chọn dạng bài phù hợp trong Thư viện dạng bài.</li>
                        <li>Dựng nội dung từng phần (câu hỏi, đáp án, cách tính điểm ghi trong tài liệu) theo đúng lược đồ của dạng bài — AI <b>không</b> viết giao diện hay code.</li>
                        <li>Hệ thống kiểm tra lại, gắn file nghe, đánh dấu chỗ AI chưa chắc để bạn duyệt trước khi lưu.</li>
                    </ol>
                </div>
            </div>
            @if ($recent->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Gần đây</h3></div>
                    <div class="list-group list-group-flush">
                        @foreach ($recent as $r)
                            <a href="{{ route($ai.'.show', [$course, $r]) }}" class="list-group-item list-group-item-action d-flex"><span class="flex-fill">{{ $r->title ?: ($r->source_name ?: 'Không tên') }}</span><span class="badge bg-secondary-lt">{{ \App\Models\AiImport::STATUSES[$r->status] }}</span></a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
<script src="{{ asset('js/chunk-upload.js') }}?v=1"></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const status = document.getElementById('ai-status'), prog = document.getElementById('ai-prog');
    const say = (t, cls) => { status.className = 'small ' + (cls || 'text-muted'); status.textContent = t; };
    const bar = (p) => { prog.classList.remove('d-none'); prog.firstElementChild.style.width = Math.round(p * 100) + '%'; };
    const post = async (url, body, isJson) => {
        const r = await fetch(url, { method: 'POST', credentials: 'same-origin', body: isJson ? JSON.stringify(body) : body,
            headers: Object.assign({ 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, isJson ? { 'Content-Type': 'application/json' } : {}) });
        const j = await r.json().catch(() => ({}));
        if (!r.ok || j.ok === false) throw new Error(j.message || (j.errors ? Object.values(j.errors).flat().join(' ') : 'Lỗi ' + r.status));
        return j;
    };

    async function sendPdf(file, urls) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = @json(asset('vendor/pdfjs/pdf.worker.min.js'));
        const pdf = await pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;
        let batch = [];
        for (let i = 1; i <= pdf.numPages; i++) {
            say('Đang đọc trang ' + i + '/' + pdf.numPages + '…'); bar(i / pdf.numPages * 0.5);
            const page = await pdf.getPage(i);
            const text = (await page.getTextContent()).items.map((it) => it.str + (it.hasEOL ? '\n' : ' ')).join('').replace(/[ \t]+/g, ' ').trim();
            const ops = await page.getOperatorList();
            const IMG = [pdfjsLib.OPS.paintImageXObject, pdfjsLib.OPS.paintInlineImageXObject, pdfjsLib.OPS.paintJpegXObject, pdfjsLib.OPS.paintImageXObjectRepeat].filter((x) => x !== undefined);
            const hasImage = ops.fnArray.some((f) => IMG.includes(f));
            if (text.length < 30 || hasImage) { // trang scan → AI đọc chữ; trang có hình → gửi kèm ảnh trang để AI nhìn tranh
                const vp = page.getViewport({ scale: 1.6 }); const canvas = document.createElement('canvas');
                canvas.width = vp.width; canvas.height = vp.height;
                await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                const blob = await new Promise((res) => canvas.toBlob(res, 'image/jpeg', 0.82));
                const fd = new FormData(); fd.append('image', blob, 'p' + i + '.jpg'); fd.append('no', i); fd.append('text', text);
                await post(urls.document, fd);
            } else {
                batch.push({ no: i, text });
            }
            if (batch.length >= 25 || (i === pdf.numPages && batch.length)) { await post(urls.document, { pages: batch }, true); batch = []; }
        }
    }

    document.getElementById('ai-go').addEventListener('click', async () => {
        const docs = Array.from(document.getElementById('ai-doc').files);
        const audios = Array.from(document.getElementById('ai-audio').files);
        if (!docs.length) return alert('Chọn tài liệu trước.');
        const btn = document.getElementById('ai-go'); btn.disabled = true;
        try {
            say('Đang tạo yêu cầu…');
            const cls = document.getElementById('ai-class');
            const urls = await post(@json(route($ai.'.store', $course)), { title: document.getElementById('ai-title').value, classroom_id: cls ? cls.value : null, source_name: docs[0].name, lesson_id: @json($lesson->id) }, true);
            const first = docs[0], ext = first.name.split('.').pop().toLowerCase();
            if (ext === 'pdf') {
                await sendPdf(first, urls);
            } else if (['docx', 'txt'].includes(ext)) {
                const fd = new FormData(); fd.append('file', first); await post(urls.document, fd); bar(0.5);
            } else {
                for (let i = 0; i < docs.length; i++) { // ảnh chụp các trang, theo thứ tự đã chọn
                    say('Đang gửi ảnh trang ' + (i + 1) + '/' + docs.length + '…'); bar((i + 1) / docs.length * 0.5);
                    const fd = new FormData(); fd.append('image', docs[i]); fd.append('no', i + 1); await post(urls.document, fd);
                }
            }
            for (let i = 0; i < audios.length; i++) {
                say('Đang tải file nghe ' + (i + 1) + '/' + audios.length + ': ' + audios[i].name);
                await ChunkUpload.send(audios[i], urls.audio, { onProgress: (p) => bar(0.5 + (i + p) / audios.length * 0.45) });
            }
            say('Đang gửi cho AI…'); bar(1);
            const j = await post(urls.begin, {}, true);
            location.href = j.url;
        } catch (e) {
            say('Lỗi: ' + e.message, 'text-danger'); btn.disabled = false;
        }
    });
})();
</script>
@endpush
