@extends('layouts.portal')

@section('title', 'Làm bài: '.$test->unit->title)

@php
    $skills = \App\Models\ExerciseSet::SKILLS;
    $icons = ['reading' => 'book', 'listening' => 'headphones', 'speaking' => 'mic', 'writing' => 'pencil'];
@endphp

@push('styles')
<style>
    .test-tabs { position: sticky; top: 0; z-index: 20; background: var(--bs-body-bg, #fff); }
    .test-tabs .nav-link { white-space: nowrap; }
    .test-tabs .nav-link .count { font-size: .75rem; }
    .test-submit-bar { position: sticky; bottom: 0; z-index: 20; background: rgba(255,255,255,.96); border-top: 1px solid #e5e7eb; }
</style>
@endpush

@section('content')
    @php $cls = $test->assignment?->classroom; @endphp
    <nav class="small mb-2"><a href="{{ route('portal.classes.index') }}">Lớp học của tôi</a>@if ($cls) / <a href="{{ route('portal.classes.show', $cls) }}">{{ $cls->name }}</a>@endif / Làm bài</nav>
    <h1 class="h4 mb-2">{{ $test->assignment?->kind === 'mock' ? $test->assignment->title : $test->unit->title }}</h1>
    @if ($test->assignment)
        <div class="alert {{ $test->assignment->mode === 'test' ? 'alert-danger' : 'alert-info' }} py-2 small d-flex flex-wrap gap-3 align-items-center">
            <span><strong>{{ \App\Models\Assignment::MODES[$test->assignment->mode] }}</strong> · {{ $test->assignment->title }}</span>
            @if ($test->assignment->max_listens)<span><i class="bi bi-headphones"></i> Được nghe tối đa {{ $test->assignment->max_listens }} lần</span>@endif
            @if ($test->deadline_at)<span class="ms-auto fs-6"><i class="bi bi-stopwatch"></i> Còn lại: <strong id="countdown" data-deadline="{{ $test->deadline_at->toIso8601String() }}">--:--</strong></span>@endif
        </div>
    @endif
    <div class="small text-muted mb-3">Bài gồm {{ count($sections) }} phần. Chuyển qua lại giữa các phần thoải mái, câu trả lời được giữ lại (tự lưu trên máy). Làm xong tất cả rồi bấm <strong>Nộp bài</strong>.</div>

    <form method="POST" action="{{ route('portal.learning.test-submit', [$course, $test]) }}" id="test-form" data-store="unit-test-{{ $test->id }}" data-max-listens="{{ $test->assignment?->max_listens ?? 0 }}">
        @csrf
        <div class="test-tabs py-2 mb-3">
            <ul class="nav nav-pills flex-nowrap overflow-auto gap-1" role="tablist">
                @foreach ($sections as $i => $sec)
                    <li class="nav-item">
                        <button type="button" class="nav-link {{ $i === 0 ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#sec-{{ $i }}" role="tab">
                            <i class="bi bi-{{ $icons[$sec['set']->skill] }}"></i> {{ $i + 1 }}. {{ $skills[$sec['set']->skill] }}
                            <span class="count badge text-bg-light border ms-1" data-count="{{ $i }}">0/{{ count($sec['questions']) }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="tab-content">
            @foreach ($sections as $i => $sec)
                <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}" id="sec-{{ $i }}" role="tabpanel" data-section="{{ $i }}">
                    @include('portal.learning.partials.section', $sec + ['course' => $course, 'prefix' => 'answers['.$sec['set']->id.']'])
                    <div class="d-flex justify-content-between mb-4">
                        @if ($i > 0)
                            <button type="button" class="btn btn-outline-secondary" data-goto="{{ $i - 1 }}"><i class="bi bi-arrow-left"></i> {{ $skills[$sections[$i - 1]['set']->skill] }}</button>
                        @else <span></span> @endif
                        @if ($i + 1 < count($sections))
                            <button type="button" class="btn btn-outline-primary" data-goto="{{ $i + 1 }}">{{ $skills[$sections[$i + 1]['set']->skill] }} <i class="bi bi-arrow-right"></i></button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="test-submit-bar py-2 px-1 d-flex flex-wrap align-items-center gap-2">
            <span class="small text-muted" id="save-note">Bài làm được tự lưu trên máy này.</span>
            <button type="submit" class="btn btn-success btn-lg ms-auto"><i class="bi bi-send"></i> Nộp bài</button>
        </div>
    </form>
@endsection

@push('scripts')
    @include('portal.learning.partials.exercise-scripts')
<script>
(function () {
    const form = document.getElementById('test-form');
    const key = form.dataset.store;
    const panes = [...form.querySelectorAll('[data-section]')];
    const skillNames = @json(collect($sections)->map(fn ($s) => $skills[$s['set']->skill])->values());

    // Chuyển tab bằng nút ← / →; dừng âm thanh khi rời phần Nghe
    form.querySelectorAll('[data-goto]').forEach((b) => b.addEventListener('click', () => {
        const btn = form.querySelector(`[data-bs-target="#sec-${b.dataset.goto}"]`);
        bootstrap.Tab.getOrCreateInstance(btn).show();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }));
    form.querySelectorAll('[data-bs-toggle="pill"]').forEach((t) => t.addEventListener('hide.bs.tab', () => {
        form.querySelectorAll('.player').forEach((p) => p.dispatchEvent(new Event('player-stop')));
    }));

    // Câu đã làm: chọn đáp án / có chữ (khác phần gợi ý cho sẵn) / đã lưu ghi âm
    function answered(card) {
        if (card.querySelector('.recorder')) return card.querySelector('.recorder').dataset.recorded === '1';
        if (card.querySelector('input[type=radio]')) return !!card.querySelector('input[type=radio]:checked');
        const f = card.querySelector('textarea, input[type=text]');
        if (!f) return false;
        const v = f.value.trim();
        return v !== '' && v !== (f.dataset.start || '').trim();
    }
    function counts() {
        return panes.map((p) => { const cards = [...p.querySelectorAll('[data-question]')]; return [cards.filter(answered).length, cards.length]; });
    }
    function refresh() {
        counts().forEach(([done, total], i) => {
            const el = form.querySelector(`[data-count="${i}"]`);
            el.textContent = done + '/' + total;
            el.className = 'count badge ms-1 ' + (done === total ? 'text-bg-success' : 'text-bg-light border');
        });
    }

    // Tự lưu câu trả lời (chữ / lựa chọn) trên máy; ghi âm đã lưu lên máy chủ khi ghi xong
    function save() {
        const data = {};
        form.querySelectorAll('input[name^="answers"], textarea[name^="answers"]').forEach((el) => {
            if (el.type === 'radio') { if (el.checked) data[el.name] = el.value; } else data[el.name] = el.value;
        });
        try { localStorage.setItem(key, JSON.stringify(data)); document.getElementById('save-note').textContent = 'Đã tự lưu lúc ' + new Date().toLocaleTimeString('vi-VN'); } catch (e) { /* bộ nhớ trình duyệt bị chặn */ }
    }
    function restore() {
        let data = {};
        try { data = JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) { return; }
        Object.entries(data).forEach(([name, value]) => {
            form.querySelectorAll(`[name="${CSS.escape(name)}"]`).forEach((el) => {
                if (el.type === 'radio') el.checked = el.value === value;
                else { el.value = value; el.dispatchEvent(new Event('restore')); }
            });
        });
    }

    restore();
    refresh();
    form.addEventListener('input', () => { refresh(); save(); });
    form.addEventListener('change', () => { refresh(); save(); });

    // Giới hạn số lần nghe (đếm cả khi tải lại trang)
    const maxListens = Number(form.dataset.maxListens || 0);
    if (maxListens) {
        const lk = key + '-listens';
        form.querySelectorAll('.player').forEach((p) => {
            const btn = p.querySelector('.player-play');
            const used = () => Number(localStorage.getItem(lk) || 0);
            const label = () => { btn.innerHTML = '<i class="bi bi-play-fill"></i> Nghe (' + Math.max(0, maxListens - used()) + ' lần)'; btn.disabled = used() >= maxListens; };
            btn.addEventListener('click', () => { try { localStorage.setItem(lk, used() + 1); } catch (e) {} setTimeout(label, 50); }, true);
            p.querySelectorAll('.audio-part').forEach((a) => { a.removeAttribute('controls'); });
            label();
        });
    }

    // Đồng hồ đếm ngược — hết giờ tự nộp
    const cd = document.getElementById('countdown');
    let autoSubmit = false;
    if (cd) {
        const end = new Date(cd.dataset.deadline).getTime();
        const tick = () => {
            const left = Math.max(0, Math.floor((end - Date.now()) / 1000));
            const p2 = (n) => String(n).padStart(2, '0');
            const d = Math.floor(left / 86400), hh = Math.floor(left % 86400 / 3600), mm = Math.floor(left % 3600 / 60);
            cd.textContent = (d ? d + ' ngày ' : '') + (d || hh ? p2(hh) + ':' : '') + p2(mm) + ':' + p2(left % 60);
            if (left <= 60) cd.classList.add('text-danger');
            if (left === 0 && !autoSubmit) { autoSubmit = true; save(); alert('Hết giờ! Bài làm được nộp tự động.'); form.requestSubmit ? form.requestSubmit() : form.submit(); }
        };
        tick();
        setInterval(tick, 1000);
    }

    form.addEventListener('submit', (ev) => {
        if (autoSubmit) { try { localStorage.removeItem(key); } catch (e) {} return; }
        const missing = counts().map(([d, t], i) => t - d > 0 ? `• ${skillNames[i]}: còn ${t - d}/${t} câu chưa làm` : null).filter(Boolean);
        const msg = (missing.length ? 'Bạn còn câu chưa làm:\n' + missing.join('\n') + '\n\nCâu chưa làm (và nhiệm vụ nói chưa ghi âm) được 0 điểm.\n\n' : '') + 'Nộp bài? Đã nộp thì không sửa được.';
        if (!confirm(msg)) { ev.preventDefault(); return; }
        try { localStorage.removeItem(key); } catch (e) { /* ignore */ }
    });
})();
</script>
@endpush
