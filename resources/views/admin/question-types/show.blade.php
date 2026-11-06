@extends('layouts.admin')

@section('title', $type->name)

@php
    use App\Support\QuestionTypes\Scoring;
    $tab = 'question-types';
@endphp

@section('content')
    @include('admin.system.partials.tabs')

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="{{ route('admin.system.question-types.index') }}" class="btn btn-ghost-secondary btn-sm"><i class="ti ti-arrow-left me-1"></i>Thư viện</a>
        <span class="font-monospace text-muted">{{ $type->code }}</span>
        <span class="badge bg-blue-lt">{{ $type->label('standard') }}</span>
        <span class="badge bg-azure-lt">{{ $type->label('skill') }}</span>
        <span class="badge bg-indigo-lt">{{ $type->label('lang') }}</span>
        <span class="badge {{ $type->grading === 'auto' ? 'bg-green-lt' : 'bg-purple-lt' }}">{{ $type->label('grading') }}</span>
        @if ($type->legacy)<span class="badge bg-orange-lt">Loại V1: {{ $type->legacy }}</span>@endif
        <form method="POST" action="{{ route('admin.system.question-types.toggle', $type->code) }}" class="ms-auto">
            @csrf
            <button class="btn btn-sm {{ $enabled ? 'btn-success' : 'btn-outline-secondary' }}"><i class="ti {{ $enabled ? 'ti-toggle-right' : 'ti-toggle-left' }} me-1"></i>{{ $enabled ? 'Đang bật — bấm để tắt' : 'Đang tắt — bấm để bật' }}</button>
        </form>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Làm thử @if ($custom)<span class="badge bg-yellow-lt ms-1">nội dung tự soạn</span>@endif</h3>
                    <div class="card-actions btn-list">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="qt-fill"><i class="ti ti-eye me-1"></i>Điền đáp án</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="qt-reset"><i class="ti ti-refresh me-1"></i>Làm lại</button>
                    </div>
                </div>
                <div class="card-body">
                    @if ($type->description)<p class="text-muted">{{ $type->description }}</p>@endif
                    @include('question-types.render', ['type' => $type, 'content' => $content, 'uid' => 'qt-try'])
                </div>
                <div class="card-footer d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-primary" id="qt-grade"><i class="ti ti-check me-1"></i>Chấm thử</button>
                    <span class="text-muted small" id="qt-status"></span>
                </div>
            </div>

            <div class="card mt-3 d-none" id="qt-result">
                <div class="card-header"><h3 class="card-title">Kết quả chấm</h3><div class="card-actions"><span class="h3 mb-0" id="qt-score"></span></div></div>
                <div class="table-responsive">
                    <table class="table table-sm card-table">
                        <thead><tr><th>Ý</th><th>Trả lời</th><th>Đáp án</th><th class="text-end">Điểm</th></tr></thead>
                        <tbody id="qt-parts"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Cách làm & cách chấm</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-4">Giao diện</dt><dd class="col-8">{{ $engine->label() }} <span class="font-monospace text-muted small">({{ $engine->key() }})</span><div class="small text-muted">{{ $engine->interaction() }}</div></dd>
                        <dt class="col-4">Chấm</dt><dd class="col-8">{{ $type->label('grading') }}<div class="small text-muted">{{ $engine->rubric($type) }}</div></dd>
                        <dt class="col-4">Tính điểm</dt>
                        <dd class="col-8">
                            {{ rtrim(rtrim(number_format($scoring['points'], 2, ',', ''), '0'), ',') }} điểm / ý ·
                            {{ $scoring['partial'] ? 'có điểm từng phần' : 'đúng hết mới có điểm' }}
                            @if ($scoring['penalty'] > 0) · trừ {{ $scoring['penalty'] }} / ý sai @endif
                            <div class="small text-muted">Quy đổi: {{ Scoring::SCALES[$scoring['scale']] ?? $scoring['scale'] }}</div>
                        </dd>
                    </dl>
                </div>
            </div>

            <form class="card mb-3" method="POST" action="{{ route('admin.system.question-types.scoring', $type->code) }}">
                @csrf
                <div class="card-header"><h3 class="card-title">Chỉnh cách tính điểm mặc định</h3></div>
                <div class="card-body row g-2">
                    <div class="col-6"><label class="form-label">Điểm mỗi ý</label><input type="number" step="0.05" min="0.05" name="points" class="form-control" value="{{ $scoring['points'] }}"></div>
                    <div class="col-6"><label class="form-label">Trừ mỗi ý sai</label><input type="number" step="0.05" min="0" name="penalty" class="form-control" value="{{ $scoring['penalty'] }}"></div>
                    <div class="col-12"><label class="form-label">Quy đổi</label>
                        <select name="scale" class="form-select">@foreach (Scoring::SCALES as $k => $l)<option value="{{ $k }}" @selected($scoring['scale'] === $k)>{{ $l }}</option>@endforeach</select>
                    </div>
                    <div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="partial" value="1" @checked($scoring['partial'])><span class="form-check-label">Cho điểm từng phần (vd chọn đúng 2/3 đáp án)</span></label></div>
                    <div class="col-12 small text-muted">Áp dụng làm mặc định cho dạng này; từng bài cụ thể vẫn ghi đè được.</div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button class="btn btn-primary btn-sm">Lưu</button>
                    @if ($override)<button class="btn btn-ghost-secondary btn-sm" name="reset" value="1">Về mặc định của dạng</button>@endif
                </div>
            </form>

            <div class="card mb-3">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs">
                        <li class="nav-item"><a href="#tab-json" class="nav-link active" data-bs-toggle="tab">JSON nội dung</a></li>
                        <li class="nav-item"><a href="#tab-answer" class="nav-link" data-bs-toggle="tab">Đáp án</a></li>
                        <li class="nav-item"><a href="#tab-schema" class="nav-link" data-bs-toggle="tab">Lược đồ</a></li>
                    </ul>
                </div>
                <div class="card-body tab-content">
                    <div class="tab-pane active show" id="tab-json">
                        <form method="POST" action="{{ route('admin.system.question-types.show', $type->code) }}">
                            @csrf
                            @foreach ($jsonErrors as $e)<div class="alert alert-danger py-1 px-2 mb-1 small">{{ $e }}</div>@endforeach
                            <textarea name="content" class="form-control font-monospace small" rows="16" spellcheck="false">{{ $json }}</textarea>
                            <div class="d-flex gap-2 mt-2">
                                <button class="btn btn-sm btn-outline-primary"><i class="ti ti-player-play me-1"></i>Dựng thử với JSON này</button>
                                @if ($custom)<a href="{{ route('admin.system.question-types.show', $type->code) }}" class="btn btn-sm btn-ghost-secondary">Về dữ liệu mẫu</a>@endif
                            </div>
                            <div class="small text-muted mt-1">Đây là dạng dữ liệu giáo viên / AI sẽ đưa vào. Sửa rồi bấm dựng thử để kiểm tra lược đồ.</div>
                        </form>
                    </div>
                    <div class="tab-pane" id="tab-answer"><pre class="small mb-0">{{ json_encode($perfect, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
                    <div class="tab-pane" id="tab-schema"><pre class="small mb-0" style="max-height: 420px; overflow: auto">{{ $schema }}</pre></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const root = document.getElementById('qt-try');
    const perfect = @json($perfect);
    const custom = @json($custom ? $json : null);
    const status = document.getElementById('qt-status');
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    document.getElementById('qt-fill').addEventListener('click', () => QT.fill(root, perfect));
    document.getElementById('qt-reset').addEventListener('click', () => location.reload());
    document.getElementById('qt-grade').addEventListener('click', async () => {
        status.textContent = 'Đang chấm…';
        const body = { answer: QT.collect(root) };
        if (custom) body.content = custom;
        try {
            const r = await fetch(@json(route('admin.system.question-types.grade', $type->code)), {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify(body),
            });
            const j = await r.json();
            if (!r.ok || !j.ok) throw new Error(j.message || 'Lỗi ' + r.status);
            QT.showResult(root, j);
            const s = j.score;
            document.getElementById('qt-score').textContent = s.pending ? 'Chờ AI / giáo viên chấm' : (s.earned + ' / ' + s.max + ' · ' + s.scaled);
            document.getElementById('qt-parts').innerHTML = j.parts.map((p) => '<tr class="' + (j.pending ? '' : (p.correct ? 'table-success' : 'table-danger')) + '">'
                + '<td>' + esc(p.label) + '</td><td>' + esc(p.given || '—') + (p.reason ? ' <span class="text-muted small">(' + esc(p.reason) + ')</span>' : '') + '</td>'
                + '<td>' + esc(p.expected) + '</td><td class="text-end">' + esc(p.earned) + ' / ' + esc(p.max) + '</td></tr>').join('');
            document.getElementById('qt-result').classList.remove('d-none');
            status.textContent = '';
        } catch (e) {
            status.textContent = 'Không chấm được: ' + e.message;
        }
    });
})();
</script>
@endpush
