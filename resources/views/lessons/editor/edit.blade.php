@extends($layout)

@section('title', $lesson->title)

@section('content')
    <div class="mb-3"><a href="{{ route($prefix.'.index', $course) }}"><i class="ti ti-arrow-left me-1"></i>Bài học của khoá “{{ $course->title }}”</a></div>
    <div class="row g-3">
        <div class="col-lg-7">
            @php $aiPrefix = str_replace('custom', 'ai-import', $customPrefix); @endphp
            <div class="card mb-3 border-primary">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h3 class="card-title text-nowrap me-auto"><i class="ti ti-checklist me-1"></i>Bài tập của bài học</h3>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route($customPrefix.'.store', $course) }}" class="d-inline">@csrf
                            <input type="hidden" name="lesson_id" value="{{ $lesson->id }}"><input type="hidden" name="title" value="Bài tập — {{ $lesson->title }}"><input type="hidden" name="skill" value="mixed">
                            <button class="btn btn-sm btn-outline-primary"><i class="ti ti-plus me-1"></i>Soạn bài tập</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-purple" data-bs-toggle="collapse" data-bs-target="#ai-compose"><i class="ti ti-wand me-1"></i>AI tự tạo bài tập</button>
                        <a href="{{ route($aiPrefix.'.create', [$course, 'lesson' => $lesson->id]) }}" class="btn btn-sm btn-primary"><i class="ti ti-sparkles me-1"></i>Tải file bài tập cho AI dựng</a>

                    </div>
                </div>
                <div class="collapse {{ $errors->has('counts') ? 'show' : '' }}" id="ai-compose">
                    <form method="POST" action="{{ route($prefix.'.ai-compose', [$course, $lesson]) }}" class="card-body border-bottom bg-purple-lt">@csrf
                        <div class="fw-semibold mb-1"><i class="ti ti-wand me-1"></i>AI tự tạo bài tập mới</div>
                        <div class="small text-muted mb-2">Dựa vào tên, tóm tắt và nội dung / ghi chú của bài học (càng ghi rõ từ vựng, ngữ pháp, chủ đề thì bài càng sát). Câu hỏi viết mới, không chép bài có sẵn. Tạo xong ở trạng thái <b>Nháp</b> để bạn duyệt.</div>
                        @error('counts')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                        <div class="row g-2 align-items-end">
                            @foreach (\App\Support\AiImport\AiImportService::COMPOSE_SKILLS as $k => $label)
                                <div class="col-6 col-md"><label class="form-label small mb-1">{{ $label }}</label>
                                    <input type="number" name="counts[{{ $k }}]" min="0" max="30" class="form-control form-control-sm" value="{{ old('counts.'.$k, ['reading' => 2, 'listening' => 2, 'speaking' => 2, 'writing' => 1, 'vocab' => 3][$k]) }}"></div>
                            @endforeach
                            <div class="col-12 col-md-auto"><button class="btn btn-purple btn-sm w-100" @disabled($generating)>Tạo</button></div>
                        </div>
                        <div class="form-hint">Nhập số <b>câu hỏi lớn</b> mỗi kỹ năng (0–30, tổng tối đa 60). Mỗi câu hỏi lớn là một phần, vd một bài đọc kèm vài câu hỏi nhỏ, một bài nghe, một đề viết, một nhiệm vụ nói; số câu nhỏ bên trong do AI chọn theo dạng bài.</div>
                    </form>
                </div>
                @if ($generating)<div class="card-body py-2 small text-primary border-bottom"><span class="spinner-border spinner-border-sm me-1"></span>AI đang tạo bài tập cho bài này…</div>@endif
                @if ($pendingImports->isNotEmpty())
                    <div class="list-group list-group-flush border-bottom">
                        @foreach ($pendingImports as $imp)
                            @php
                                $secs = collect($imp->sections ?? []);
                                $color = ['review' => 'warning', 'failed' => 'danger'][$imp->status] ?? 'primary';
                            @endphp
                            <div class="list-group-item d-flex align-items-center gap-2 bg-{{ $color }}-lt">
                                <a href="{{ route($aiPrefix.'.show', [$course, $imp]) }}" class="flex-fill text-reset text-decoration-none">
                                    <span class="badge bg-{{ $color }} text-white me-1">{{ \App\Models\AiImport::STATUSES[$imp->status] ?? $imp->status }}</span>
                                    <span class="fw-semibold">{{ $imp->title ?: $imp->source_name }}</span>
                                    <span class="small text-muted d-block">
                                        {{ $imp->mode === 'compose' ? 'AI tự tạo' : 'AI dựng từ file '.$imp->source_name }}
                                        @if ($secs->isNotEmpty()) · {{ $secs->where('status', 'ok')->count() }}/{{ $secs->count() }} phần hợp lệ @endif
                                        · {{ $imp->created_at->format('d/m H:i') }}
                                        @if ($imp->status === 'review') · <b>chưa thành bài tập — mở để duyệt và bấm Lưu</b> @endif
                                        @if ($imp->status === 'failed' && $imp->error) · {{ \Illuminate\Support\Str::limit($imp->error, 120) }} @endif
                                    </span>
                                </a>
                                <a href="{{ route($aiPrefix.'.show', [$course, $imp]) }}" class="btn btn-sm {{ $imp->status === 'review' ? 'btn-warning' : 'btn-outline-secondary' }}">{{ $imp->status === 'review' ? 'Duyệt & lưu' : 'Xem' }}</a>
                                <form method="POST" action="{{ route($aiPrefix.'.destroy', [$course, $imp]) }}" onsubmit="return confirm('Xoá bản AI dựng này? Nội dung chưa lưu thành bài tập sẽ mất.')">@csrf @method('DELETE')<input type="hidden" name="from" value="lesson">
                                    <button class="btn btn-sm btn-ghost-danger" title="Xoá bản AI dựng"><i class="ti ti-trash"></i></button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="list-group list-group-flush">
                    @forelse ($lesson->exercises->whereNull('source_classroom_id') as $e)
                        <div class="list-group-item d-flex align-items-center gap-2 {{ $e->status === 'hidden' ? 'opacity-50' : '' }}">
                            <a href="{{ route($customPrefix.'.edit', [$course, $e]) }}" class="flex-fill text-reset text-decoration-none"><span class="fw-semibold">{{ $e->title }}</span>
                                <span class="small text-muted d-block">{{ $e->items->count() }} phần · {{ $e->items->map(fn ($i) => \App\Support\QuestionTypes\Registry::find($i->type_code)?->name)->filter()->unique()->take(3)->implode(', ') }}@if ($e->source === 'ai') · AI @endif</span></a>
                            <a href="{{ route($customPrefix.'.preview', [$course, $e]) }}" target="_blank" class="btn btn-sm btn-ghost-secondary" title="Xem như học viên"><i class="ti ti-eye"></i></a>
                            <form method="POST" action="{{ route($customPrefix.'.status', [$course, $e]) }}">@csrf @method('PATCH')
                                <select name="status" class="form-select form-select-sm {{ ['draft' => '', 'published' => 'border-success text-success', 'hidden' => 'border-danger text-danger'][$e->status] }}" style="width: 9.5rem" onchange="this.form.submit()" title="Đổi trạng thái">
                                    <option value="draft" @selected($e->status === 'draft')>Nháp</option>
                                    <option value="published" @selected($e->status === 'published')>Xuất bản</option>
                                    <option value="hidden" @selected($e->status === 'hidden')>Ẩn</option>
                                </select>
                            </form>
                            <form method="POST" action="{{ route($customPrefix.'.destroy', [$course, $e]) }}" onsubmit="return confirm('Huỷ bài tập “{{ $e->title }}”? {{ $e->locked() ? 'Bài đã có học viên làm nên sẽ được ẩn (giữ kết quả).' : 'Bài sẽ bị xoá.' }}')">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-ghost-danger" title="Huỷ bài tập"><i class="ti ti-trash"></i></button>
                            </form>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">@if ($pendingImports->isNotEmpty())Chưa có bài tập đã lưu (bản AI dựng ở trên cần duyệt và lưu). @endif Bài học chưa có bài tập. Học viên học qua bài tập — hãy soạn bài tập hoặc tải file bài tập cho AI dựng.</div>
                    @endforelse
                </div>
                @php $classOwn = $lesson->exercises->whereNotNull('source_classroom_id'); @endphp
                @if ($classOwn->isNotEmpty())<div class="card-footer small text-muted">Ngoài ra có {{ $classOwn->count() }} bài tập riêng do giáo viên tạo cho lớp của mình.</div>@endif
            </div>

            <form method="POST" action="{{ route($prefix.'.update', [$course, $lesson]) }}" class="card mb-3">@csrf @method('PUT')
                <div class="card-header"><h3 class="card-title">Nội dung bài học</h3></div>
                <div class="card-body">
                    <div class="mb-2"><label class="form-label required">Tên bài</label><input name="title" class="form-control" value="{{ old('title', $lesson->title) }}" required maxlength="200"></div>

                    <div class="mb-2"><label class="form-label">Tóm tắt</label><textarea name="summary" class="form-control" rows="2">{{ old('summary', $lesson->summary) }}</textarea></div>
                    <div class="mb-2"><label class="form-label">Ghi chú bài học (từ vựng, ngữ pháp, dặn dò…)</label><textarea name="content" class="form-control" rows="8">{{ old('content', $lesson->content) }}</textarea></div>
                    <label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="require_video" value="1" @checked($lesson->require_video)><span class="form-check-label">Phải xem xong video ({{ config('video.complete_percent') }}%) mới làm bài tập của bài</span></label>
                    <input type="hidden" name="is_published" value="0">
                    <label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_published" value="1" @checked($lesson->is_published)><span class="form-check-label">Hiện với học viên</span></label>
                </div>
                <div class="card-footer d-flex"><button class="btn btn-primary">Lưu</button></div>
            </form>

            {{-- Từ vựng của bài: học viên ôn bằng thẻ ghi nhớ (mục "Ôn từ vựng"), AI lập sẵn từ nội dung bài --}}
            @php $vzh = $course->language === 'zh'; $vcount = count($lesson->vocabulary ?? []); @endphp
            <div class="card mb-3" id="vocabulary">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h3 class="card-title me-auto"><i class="ti ti-cards me-1"></i>Từ vựng của bài <span class="text-muted small fw-normal">({{ $vcount }} từ · học viên ôn bằng thẻ)</span></h3>
                    <form method="POST" action="{{ route($prefix.'.vocabulary.ai', [$course, $lesson]) }}" class="js-vocab-ai" data-confirm="{{ $vcount ? 'AI lập lại danh sách sẽ thay danh sách hiện tại. Tiếp tục?' : '' }}">@csrf
                        <button class="btn btn-sm btn-purple"><i class="ti ti-wand me-1"></i>{{ $vcount ? 'AI lập lại danh sách' : 'AI lập danh sách từ vựng' }}</button>
                    </form>
                </div>
                <form method="POST" action="{{ route($prefix.'.vocabulary', [$course, $lesson]) }}">@csrf @method('PUT')
                    <div class="card-body">
                        @error('vocabulary')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                        <textarea name="vocabulary" class="form-control font-monospace small" rows="{{ min(16, max(5, $vcount + 1)) }}" spellcheck="false"
                            placeholder="{{ $vzh ? "你好 | nǐ hǎo | xin chào | 你好，老师！
谢谢 | xièxie | cảm ơn | 谢谢你。" : "friendship | n. | tình bạn | Friendship is important.
loyal | adj. | trung thành | He is a loyal friend." }}">{{ old('vocabulary', \App\Support\Vocab\LessonVocabulary::toText($lesson)) }}</textarea>
                        <div class="form-hint">Mỗi dòng một từ: <code>{{ $vzh ? 'chữ Hán | pinyin | nghĩa | ví dụ' : 'từ | từ loại | nghĩa | ví dụ' }}</code> (chỉ cột đầu là bắt buộc). Bấm “AI lập danh sách” để AI đọc nội dung bài và điền sẵn.</div>
                    </div>
                    <div class="card-footer d-flex"><button class="btn btn-primary">Lưu từ vựng</button></div>
                </form>
            </div>
            @push('scripts')
            <script>
            document.querySelectorAll('form.js-vocab-ai').forEach((f) => f.addEventListener('submit', (e) => {
                if (f.dataset.confirm && !confirm(f.dataset.confirm)) return e.preventDefault();
                const b = f.querySelector('button'); b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>AI đang lập… (khoảng 10–20 giây)';
            }));
            </script>
            @endpush

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Tài liệu (PDF, ảnh, Word…)</h3></div>
                <div class="list-group list-group-flush">
                    @forelse ($lesson->materials as $m)
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <i class="ti ti-file"></i><span class="flex-fill">{{ $m->title }} <span class="text-muted small">{{ round($m->size / 1024) }} KB</span></span>
                            <form method="POST" action="{{ route($prefix.'.material.destroy', [$course, $lesson, $m]) }}" onsubmit="return confirm('Xoá tài liệu?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger"><i class="ti ti-trash"></i></button></form>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">Chưa có tài liệu.</div>
                    @endforelse
                </div>
                <form method="POST" action="{{ route($prefix.'.material.upload', [$course, $lesson]) }}" enctype="multipart/form-data" class="card-footer d-flex flex-wrap gap-2">@csrf
                    <input name="title" class="form-control w-auto flex-fill" placeholder="Tên tài liệu (tuỳ chọn)">
                    <input type="file" name="file" class="form-control w-auto" required>
                    <button class="btn btn-outline-primary">Tải lên</button>
                    <div class="w-100 form-hint">Tối đa 7,9 MB / file: {{ implode(', ', \App\Models\LessonMaterial::EXTENSIONS) }}.</div>
                </form>
            </div>

<div class="text-end"><button type="button" class="btn btn-sm btn-outline-danger"
                    data-delete-lesson="{{ route($prefix.'.destroy', [$course, $lesson]) }}" data-title="{{ $lesson->title }}"
                    data-exercises="{{ $lesson->exercises()->count() }}" data-videos="{{ $lesson->videos->count() }}" data-materials="{{ $lesson->materials->count() }}" data-attempts="{{ $attempts }}"><i class="ti ti-trash me-1"></i>Xoá bài học</button></div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Video hướng dẫn</h3></div>
                <div class="list-group list-group-flush">
                    @forelse ($lesson->videos as $v)
                        <div class="list-group-item">
                            <form method="POST" action="{{ route($prefix.'.video.update', [$course, $lesson, $v]) }}" enctype="multipart/form-data">@csrf @method('PUT')
                                <div class="d-flex gap-2 align-items-center mb-1">
                                    <span class="badge bg-blue-lt">{{ ['local' => 'MP4', 'hls' => 'HLS', 'bunny' => 'Bunny'][$v->provider] }}</span>
                                    <input name="title" class="form-control form-control-sm" value="{{ $v->title }}" required>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center small">
                                    <span class="text-muted">{{ $v->duration_seconds ? gmdate($v->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $v->duration_seconds) : '—' }}</span>
                                    @if ($v->size)<span class="text-muted">{{ round($v->size / 1048576, 1) }} MB</span>@endif
                                    <span class="text-muted">{{ $v->captions_path ? '✓ có phụ đề' : 'chưa có phụ đề' }}</span>
                                    <input type="file" name="captions" class="form-control form-control-sm w-auto" accept=".vtt,.srt" title="Phụ đề .vtt / .srt">
                                    <button class="btn btn-sm btn-outline-primary">Lưu</button>
                                    @if ($v->provider === 'local')<a href="{{ route($prefix.'.video.preview', [$course, $lesson, $v]) }}" target="_blank" class="btn btn-sm btn-ghost-secondary">Xem</a>@endif
                                </div>
                            </form>
                            <form method="POST" action="{{ route($prefix.'.video.destroy', [$course, $lesson, $v]) }}" class="text-end" onsubmit="return confirm('Xoá video?')">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0">Xoá video</button></form>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">Chưa có video.</div>
                    @endforelse
                </div>
                <div class="card-body border-top">
                    <div class="fw-semibold mb-1">Tải video lên</div>
                    <div class="d-flex flex-wrap gap-2">
                        <select id="v-kind" class="form-select w-auto"><option value="local">MP4 (phát theo đoạn)</option><option value="hls">Gói HLS mã hoá (.zip)</option></select>
                        <input type="file" id="v-file" class="form-control w-auto flex-fill" accept=".mp4,.m4v,.webm,.mov,.zip">
                        <button type="button" class="btn btn-primary" id="v-send">Tải lên</button>
                    </div>
                    <div class="progress mt-2 d-none" id="v-prog"><div class="progress-bar" style="width: 0%"></div></div>
                    <div class="small mt-1" id="v-status"></div>
                    <details class="small mt-2">
                        <summary>Mẹo: video không lag, tua nhanh, nhiều độ phân giải</summary>
                        <ul class="mb-0 mt-1">
                            <li><b>MP4:</b> xuất với "fast start" để phát ngay: <code>ffmpeg -i in.mp4 -c copy -movflags +faststart out.mp4</code>. Tối đa {{ $maxMb }} MB.</li>
                            <li><b>HLS mã hoá AES-128</b> (chia đoạn, chống tải lậu tốt hơn): tạo trên máy tính rồi nén thư mục thành .zip:<br>
                                <code>openssl rand 16 &gt; enc.key</code>, tạo <code>enc.keyinfo</code> gồm 2 dòng <code>enc.key</code> / <code>enc.key</code>, rồi<br>
                                <code>ffmpeg -i in.mp4 -c:v libx264 -c:a aac -hls_time 6 -hls_key_info_file enc.keyinfo -hls_playlist_type vod index.m3u8</code></li>
                            <li><b>Nhiều độ phân giải + DRM:</b> dùng Bunny Stream (tự chuyển đổi 360p/720p/1080p).</li>
                        </ul>
                    </details>
                </div>
                @if ($bunny)
                    <form method="POST" action="{{ route($prefix.'.video.bunny', [$course, $lesson]) }}" class="card-footer">@csrf
                        <div class="fw-semibold mb-1">Hoặc thêm video Bunny Stream</div>
                        <div class="d-flex flex-wrap gap-2">
                            <input name="title" class="form-control w-auto flex-fill" placeholder="Tên video" required>
                            <input name="external_id" class="form-control w-auto flex-fill font-monospace" placeholder="GUID video" required>
                            <input name="duration_seconds" type="number" class="form-control" style="width: 7rem" placeholder="Giây">
                            <button class="btn btn-outline-primary">Thêm</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
    @include('lessons.editor.partials.delete-modal')
@endsection

@push('scripts')
<script src="{{ asset('js/chunk-upload.js') }}?v=1"></script>
<script>
document.getElementById('v-send').addEventListener('click', async () => {
    const file = document.getElementById('v-file').files[0];
    const kind = document.getElementById('v-kind').value;
    const status = document.getElementById('v-status');
    const prog = document.getElementById('v-prog');
    if (!file) return alert('Chọn file trước.');
    if (file.size > {{ $maxMb }} * 1048576) return alert('File vượt {{ $maxMb }} MB.');
    const btn = document.getElementById('v-send'); btn.disabled = true;
    prog.classList.remove('d-none');
    status.className = 'small mt-1 text-muted'; status.textContent = 'Đang tải lên…';
    try {
        const duration = kind === 'local' ? await ChunkUpload.duration(file) : 0;
        await ChunkUpload.send(file, @json(route($prefix.'.video.upload', [$course, $lesson])), {
            params: { kind, duration },
            onProgress: (p) => { prog.firstElementChild.style.width = Math.round(p * 100) + '%'; status.textContent = 'Đang tải lên… ' + Math.round(p * 100) + '%'; },
        });
        status.className = 'small mt-1 text-success'; status.textContent = 'Xong. Đang tải lại trang…';
        location.reload();
    } catch (e) {
        status.className = 'small mt-1 text-danger'; status.textContent = 'Lỗi: ' + e.message;
        btn.disabled = false;
    }
});
</script>
@endpush
