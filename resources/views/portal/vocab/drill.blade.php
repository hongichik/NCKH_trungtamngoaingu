@extends('layouts.portal')

@section('title', 'Luyện tiếng Trung · '.$unit->title)

@push('styles')
<style>
    .drill-big { font-size: 3.4rem; line-height: 1.2; }
    .drill-opt { min-width: 120px; font-size: 1.15rem; }
    .drill-opt.zh { font-size: 1.6rem; }
</style>
@endpush

@section('content')
    <nav class="small mb-2"><a href="{{ route('portal.vocab.index') }}">Ôn từ vựng</a> / Luyện tiếng Trung · {{ $unit->title }}</nav>
    <h1 class="h4 mb-3">Luyện tiếng Trung — {{ $unit->title }}</h1>

    @if (empty($questions))
        <div class="alert alert-info">Unit cần ít nhất 2 từ có chữ Hán và pinyin để tạo bài luyện.</div>
    @else
        <div class="d-flex align-items-center gap-2 mb-3 small text-muted">
            <span id="dr-pos"></span>
            <div class="progress flex-fill" style="height: 6px"><div class="progress-bar bg-success" id="dr-bar" style="width: 0"></div></div>
            <span id="dr-score"></span>
        </div>
        <div class="mx-auto" style="max-width: 640px">
            <div class="card shadow-sm border-0 mb-3" id="dr-card">
                <div class="card-body text-center py-4">
                    <span class="badge text-bg-light border mb-2" id="dr-label"></span>
                    <div class="mb-2" id="dr-prompt"></div>
                    <div class="drill-big mb-2 d-none" id="dr-big"></div>
                    <button type="button" class="btn btn-outline-primary mb-3 d-none" id="dr-audio"><i class="bi bi-volume-up"></i> Nghe</button>
                    <div class="d-flex flex-wrap justify-content-center gap-2" id="dr-options"></div>
                    <form class="d-none mx-auto" style="max-width: 320px" id="dr-type">
                        <div class="input-group">
                            <input class="form-control form-control-lg text-center" id="dr-input" lang="zh-CN" autocomplete="off" placeholder="Gõ chữ Hán…">
                            <button class="btn btn-primary">Kiểm tra</button>
                        </div>
                        <div class="small text-muted mt-1" id="dr-hint"></div>
                    </form>
                    <div class="mt-3" id="dr-feedback"></div>
                    <button type="button" class="btn btn-primary mt-2 d-none" id="dr-next">Câu tiếp <i class="bi bi-arrow-right"></i></button>
                </div>
            </div>
            <div class="text-center d-none" id="dr-done">
                <div class="alert alert-success"><i class="bi bi-trophy"></i> Xong! Đúng <strong id="dr-final"></strong> câu.</div>
                <a href="{{ route('portal.vocab.index') }}" class="btn btn-outline-secondary">Về trang ôn từ vựng</a>
                <a href="{{ url()->current() }}" class="btn btn-primary">Luyện lượt mới</a>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
@if (! empty($questions))
<script src="{{ asset('js/speech.js') }}?v=2"></script>
<script>
(function () {
    const qs = {!! json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    const $ = (id) => document.getElementById(id);
    let i = 0, correct = 0, answered = false;
    const norm = (s) => String(s || '').replace(/[\s，。！？、,.!?]/g, '');
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const play = () => qs[i].audio && AppSpeech.speakNow(qs[i].audio, 'zh-CN', 0.75);

    function show() {
        const q = qs[i];
        answered = false;
        $('dr-pos').textContent = (i + 1) + '/' + qs.length;
        $('dr-bar').style.width = (i / qs.length * 100) + '%';
        $('dr-score').textContent = 'Đúng ' + correct;
        $('dr-label').textContent = q.label;
        $('dr-prompt').textContent = q.prompt;
        $('dr-big').textContent = q.big || '';
        $('dr-big').classList.toggle('d-none', !q.big);
        $('dr-audio').classList.toggle('d-none', !q.audio);
        $('dr-feedback').innerHTML = '';
        $('dr-next').classList.add('d-none');
        const opts = $('dr-options');
        opts.innerHTML = '';
        $('dr-type').classList.toggle('d-none', q.type !== 'dictation');
        if (q.type === 'dictation') {
            $('dr-input').value = '';
            $('dr-hint').textContent = q.hint ? 'Gợi ý nghĩa: ' + q.hint : '';
            setTimeout(() => $('dr-input').focus(), 50);
        } else {
            q.options.forEach((o) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'btn btn-outline-secondary drill-opt' + (/\p{Script=Han}/u.test(o) ? ' zh' : '');
                b.textContent = o;
                b.addEventListener('click', () => check(o, b));
                opts.appendChild(b);
            });
        }
        if (q.type === 'listen' || q.type === 'dictation') setTimeout(play, 300);
    }
    function check(given, btn) {
        if (answered) return;
        answered = true;
        const q = qs[i];
        const ok = q.type === 'dictation' ? norm(given) === norm(q.answer) : given === q.answer;
        if (ok) correct++;
        document.querySelectorAll('#dr-options .btn').forEach((b) => {
            b.disabled = true;
            if (b.textContent === q.answer) b.className = b.className.replace('btn-outline-secondary', 'btn-success');
        });
        if (btn && !ok) btn.className = btn.className.replace('btn-outline-secondary', 'btn-danger');
        $('dr-feedback').innerHTML = (ok ? '<span class="text-success fw-semibold"><i class="bi bi-check-circle"></i> Chính xác!</span>' : '<span class="text-danger fw-semibold"><i class="bi bi-x-circle"></i> Chưa đúng.</span> Đáp án: <strong>' + esc(q.answer) + '</strong>')
            + (q.reveal ? '<div class="small text-muted mt-1">' + esc(q.reveal) + '</div>' : '');
        $('dr-next').classList.remove('d-none');
        if (q.type !== 'listen' && q.type !== 'dictation' && q.audio) play();
    }
    function next() {
        i++;
        if (i >= qs.length) return finish();
        show();
    }
    function finish() {
        $('dr-card').classList.add('d-none');
        $('dr-done').classList.remove('d-none');
        $('dr-final').textContent = correct + '/' + qs.length;
        $('dr-bar').style.width = '100%';
        fetch(@json(route('portal.vocab.progress')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
            body: JSON.stringify({ kind: 'drill', unit_id: @json($unit->id), correct: correct, total: qs.length }) }).then((r) => r.ok ? r.json() : null).then(appReward).catch(() => {});
    }

    $('dr-audio').addEventListener('click', play);
    $('dr-next').addEventListener('click', next);
    $('dr-type').addEventListener('submit', (e) => { e.preventDefault(); check($('dr-input').value); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Enter' && answered && e.target.id !== 'dr-input') next(); });
    show();
})();
</script>
@endif
@endpush
