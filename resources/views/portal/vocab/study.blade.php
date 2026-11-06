@extends('layouts.portal')

@section('title', 'Học thẻ từ vựng')

@push('styles')
<style>
    .flashcard { min-height: 260px; cursor: pointer; perspective: 1000px; }
    .flashcard .face { min-height: 260px; }
    .flashcard .word { font-size: 2.2rem; font-weight: 600; line-height: 1.2; }
    .flashcard .word.zh { font-size: 3.2rem; }
    .grade-btns .btn { min-width: 84px; }
</style>
@endpush

@section('content')
    <nav class="small mb-2"><a href="{{ route('portal.vocab.index') }}">Ôn từ vựng</a> / {{ $unit?->title ?? 'Tất cả bài' }}</nav>

    @if (empty($cards))
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> Không còn thẻ nào cần ôn lúc này. Quay lại sau nhé!</div>
        <a href="{{ route('portal.vocab.index') }}" class="btn btn-outline-secondary">Về trang ôn từ vựng</a>
    @else
        <div class="d-flex align-items-center gap-2 mb-2 small text-muted">
            <span id="fc-progress"></span>
            <div class="progress flex-fill" style="height: 6px"><div class="progress-bar" id="fc-bar" style="width: 0"></div></div>
        </div>

        <div class="mx-auto" style="max-width: 560px">
            <div class="card shadow-sm border-0 flashcard mb-3" id="fc-card">
                <div class="card-body face d-flex flex-column justify-content-center align-items-center text-center">
                    <span class="badge text-bg-warning mb-2 d-none" id="fc-new">Từ mới</span>
                    <div class="word" id="fc-word"></div>
                    <div class="text-muted fs-5 d-none" id="fc-pinyin"></div>
                    <button type="button" class="btn btn-link" id="fc-speak" title="Nghe"><i class="bi bi-volume-up fs-3"></i></button>
                    <div id="fc-back" class="d-none w-100">
                        <hr>
                        <div class="small text-muted" id="fc-pos"></div>
                        <div class="fs-4 fw-semibold mb-2" id="fc-meaning"></div>
                        <div class="fst-italic text-muted" id="fc-example"></div>
                    </div>
                    <div class="small text-muted mt-3" id="fc-hint">Nhớ nghĩa của từ, rồi bấm vào thẻ (hoặc phím cách) để lật</div>
                </div>
            </div>

            <div class="grade-btns d-flex flex-wrap justify-content-center gap-2 d-none" id="fc-grades">
                <button type="button" class="btn btn-outline-danger" data-grade="again">Quên<br><span class="small">1</span></button>
                <button type="button" class="btn btn-outline-warning" data-grade="hard">Khó<br><span class="small">2</span></button>
                <button type="button" class="btn btn-outline-primary" data-grade="good">Nhớ<br><span class="small">3</span></button>
                <button type="button" class="btn btn-outline-success" data-grade="easy">Dễ<br><span class="small">4</span></button>
            </div>
            <div class="text-center d-none" id="fc-done">
                <div class="alert alert-success"><i class="bi bi-trophy"></i> Xong lượt ôn! <span id="fc-stats"></span></div>
                <a href="{{ route('portal.vocab.index') }}" class="btn btn-outline-secondary">Về trang ôn từ vựng</a>
                <a href="{{ url()->current().'?'.request()->getQueryString() }}" class="btn btn-primary">Ôn tiếp</a>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script src="{{ asset('js/speech.js') }}?v=2"></script>
<script>
(function () {
    const cards = {!! json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    if (!cards.length) return;
    const url = @json(route('portal.vocab.review'));
    const token = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    const $ = (id) => document.getElementById(id);
    const queue = cards.slice();
    const total = queue.length;
    let done = 0, remembered = 0, flipped = false, cur = null, xpTotal = 0;

    function show() {
        cur = queue.shift();
        if (!cur) { $('fc-card').classList.add('d-none'); $('fc-grades').classList.add('d-none'); $('fc-done').classList.remove('d-none'); $('fc-stats').textContent = `Nhớ ${remembered}/${done} lượt.` + (xpTotal ? ` +${xpTotal} XP` : ''); $('fc-progress').textContent = `${total}/${total}`; $('fc-bar').style.width = '100%'; return; }
        flipped = false;
        const zh = cur.lang === 'zh-CN';
        $('fc-word').textContent = cur.word;
        $('fc-word').classList.toggle('zh', zh);
        $('fc-pinyin').textContent = cur.pinyin || '';
        $('fc-pinyin').classList.add('d-none');
        $('fc-new').classList.toggle('d-none', !cur.new);
        $('fc-pos').textContent = cur.pos || '';
        $('fc-meaning').textContent = cur.meaning || '';
        $('fc-example').textContent = cur.example || '';
        $('fc-back').classList.add('d-none');
        $('fc-grades').classList.add('d-none');
        $('fc-hint').classList.remove('d-none');
        $('fc-progress').textContent = `${Math.min(done, total)}/${total}`;
        $('fc-bar').style.width = Math.min(100, done / total * 100) + '%';
    }
    function flip() {
        if (flipped || !cur) return;
        flipped = true;
        $('fc-back').classList.remove('d-none');
        if (cur.pinyin) $('fc-pinyin').classList.remove('d-none');
        $('fc-grades').classList.remove('d-none');
        $('fc-hint').classList.add('d-none');
        speak();
    }
    function speak() { if (cur) AppSpeech.speakNow(cur.word, cur.lang, 0.85); }
    function grade(g) {
        if (!flipped || !cur) return;
        const card = cur;
        fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ deck: card.deck, key: card.key, grade: g }) })
            .then((r) => r.ok ? r.json() : null).then((r) => { if (r && r.badges && r.badges.length) appReward({ badges: r.badges }); xpTotal += (r && r.xp) || 0; }).catch(() => {});
        if (g === 'again') { queue.push(Object.assign({}, card, { new: false })); } else { done++; if (g !== 'hard') remembered++; }
        show();
    }

    $('fc-card').addEventListener('click', (e) => { if (e.target.closest('#fc-speak')) return; flip(); });
    $('fc-speak').addEventListener('click', speak);
    document.querySelectorAll('[data-grade]').forEach((b) => b.addEventListener('click', () => grade(b.dataset.grade)));
    document.addEventListener('keydown', (e) => {
        if (e.code === 'Space') { e.preventDefault(); flip(); }
        const g = { Digit1: 'again', Digit2: 'hard', Digit3: 'good', Digit4: 'easy' }[e.code];
        if (g) grade(g);
    });
    show();
})();
</script>
@endpush
