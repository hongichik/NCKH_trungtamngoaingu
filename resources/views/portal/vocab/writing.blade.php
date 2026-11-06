@extends('layouts.portal')

@section('title', 'Tập viết chữ Hán · '.$unit->title)

@push('styles')
<style>
    #hw-target { width: 280px; height: 280px; touch-action: none; background:
        linear-gradient(#e5e7eb, #e5e7eb) center/100% 1px no-repeat,
        linear-gradient(#e5e7eb, #e5e7eb) center/1px 100% no-repeat, #fff; border: 2px solid #d1d5db; border-radius: 8px; }
    .char-pick { font-size: 1.4rem; min-width: 52px; }
</style>
@endpush

@section('content')
    <nav class="small mb-2"><a href="{{ route('portal.vocab.index') }}">Ôn từ vựng</a> / Tập viết · {{ $unit->title }}</nav>
    <h1 class="h4 mb-3">Tập viết chữ Hán — {{ $unit->title }}</h1>

    @if (empty($chars))
        <div class="alert alert-info">Unit này chưa có chữ Hán trong danh sách từ vựng.</div>
    @else
        <div class="row g-4">
            <div class="col-lg-5 text-center">
                <div class="mb-2"><span class="fs-5" id="hw-pinyin"></span> · <span id="hw-meaning" class="text-muted"></span></div>
                <div class="small text-muted mb-2">Trong từ: <strong id="hw-word"></strong></div>
                <div id="hw-target" class="mx-auto mb-3"></div>
                <div class="d-flex flex-wrap justify-content-center gap-2 mb-2">
                    <button type="button" class="btn btn-outline-secondary" id="hw-animate"><i class="bi bi-play-circle"></i> Xem cách viết</button>
                    <button type="button" class="btn btn-primary" id="hw-quiz"><i class="bi bi-pencil"></i> Tự viết</button>
                    <button type="button" class="btn btn-outline-secondary" id="hw-outline"><i class="bi bi-eye"></i> Ẩn/hiện chữ mờ</button>
                    <button type="button" class="btn btn-outline-secondary" id="hw-speak"><i class="bi bi-volume-up"></i></button>
                </div>
                <div id="hw-result" class="small"></div>
                <div class="d-flex justify-content-between mt-3">
                    <button type="button" class="btn btn-link" id="hw-prev"><i class="bi bi-arrow-left"></i> Chữ trước</button>
                    <span class="small text-muted align-self-center" id="hw-pos"></span>
                    <button type="button" class="btn btn-link" id="hw-next">Chữ sau <i class="bi bi-arrow-right"></i></button>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="small text-muted mb-2">Chọn chữ để luyện (chữ có dấu ✓ là đã viết đúng trong lần này):</div>
                <div class="d-flex flex-wrap gap-2" id="hw-list">
                    @foreach ($chars as $i => $c)
                        <button type="button" class="btn btn-light border char-pick" data-i="{{ $i }}" title="{{ $c['pinyin'] }} — {{ $c['meaning'] }}">{{ $c['char'] }}</button>
                    @endforeach
                </div>
                <div class="alert alert-light border small mt-3 mb-0">
                    <strong>Cách luyện:</strong> bấm <em>Xem cách viết</em> để xem thứ tự nét, rồi <em>Tự viết</em> — dùng chuột hoặc ngón tay vẽ từng nét vào ô. Viết sai nét 3 lần thì máy gợi ý nét đúng. Tắt chữ mờ để tự viết từ trí nhớ.
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
@if (! empty($chars))
<script src="https://cdn.jsdelivr.net/npm/hanzi-writer@3.7.2/dist/hanzi-writer.min.js"></script>
<script src="{{ asset('js/speech.js') }}?v=2"></script>
<script>
(function () {
    const chars = {!! json_encode($chars, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    const $ = (id) => document.getElementById(id);
    let i = 0, writer = null, outline = true;

    function load(n) {
        i = (n + chars.length) % chars.length;
        const c = chars[i];
        $('hw-pinyin').textContent = c.pinyin || '';
        $('hw-meaning').textContent = c.meaning || '';
        $('hw-word').textContent = c.word;
        $('hw-pos').textContent = (i + 1) + '/' + chars.length;
        $('hw-result').textContent = '';
        document.querySelectorAll('.char-pick').forEach((b) => b.classList.toggle('btn-primary', +b.dataset.i === i));
        $('hw-target').innerHTML = '';
        writer = HanziWriter.create('hw-target', c.char, {
            width: 280, height: 280, padding: 20, showOutline: outline, strokeAnimationSpeed: 1, delayBetweenStrokes: 250,
            showHintAfterMisses: 3, highlightOnComplete: true,
            onLoadCharDataError: () => { $('hw-result').innerHTML = '<span class="text-danger">Không tải được dữ liệu nét của chữ này.</span>'; },
        });
    }
    function quiz() {
        if (!writer) return;
        $('hw-result').textContent = 'Vẽ từng nét theo đúng thứ tự…';
        writer.quiz({
            onComplete: (s) => {
                const ok = s.totalMistakes === 0;
                $('hw-result').innerHTML = ok ? '<span class="text-success fw-semibold"><i class="bi bi-check-circle"></i> Chuẩn! Không sai nét nào.</span>'
                    : `<span class="text-warning fw-semibold">Xong — sai ${s.totalMistakes} lần. Luyện lại cho nhớ nhé.</span>`;
                const b = document.querySelector(`.char-pick[data-i="${i}"]`);
                if (ok && b && !b.textContent.includes('✓')) {
                    b.textContent += '✓';
                    fetch(@json(route('portal.vocab.progress')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ kind: 'hanzi', unit_id: @json($unit->id), correct: 1, total: 1 }) }).then((r) => r.ok ? r.json() : null).then(appReward).catch(() => {});
                }
            },
        });
    }

    $('hw-animate').addEventListener('click', () => writer && writer.animateCharacter());
    $('hw-quiz').addEventListener('click', quiz);
    $('hw-outline').addEventListener('click', () => { outline = !outline; outline ? writer.showOutline() : writer.hideOutline(); });
    $('hw-speak').addEventListener('click', () => AppSpeech.speakNow(chars[i].char, 'zh-CN', 0.8));
    $('hw-prev').addEventListener('click', () => load(i - 1));
    $('hw-next').addEventListener('click', () => load(i + 1));
    document.querySelectorAll('.char-pick').forEach((b) => b.addEventListener('click', () => load(+b.dataset.i)));
    load(0);
})();
</script>
@endif
@endpush
