@extends('layouts.portal')

@section('title', $exercise->title)

@section('content')
    <nav class="small mb-2"><a href="{{ route('portal.classes.show', $cce->classroom) }}">{{ $cce->classroom->name }}</a> / Bài tập</nav>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0 flex-fill">{{ $exercise->title }}</h1>
        @if ($attempt->deadline_at)
            <span class="badge text-bg-danger fs-6" id="timer" data-deadline="{{ $attempt->deadline_at->timestamp }}">--:--</span>
        @endif
        <span class="small text-muted" id="autosave"></span>
    </div>
    @if ($exercise->description)<p class="text-muted">{{ $exercise->description }}</p>@endif

    @include('custom.partials.items', ['exercise' => $exercise, 'prefixUid' => 'ci-', 'audioUrl' => fn ($item) => $item->audio_path ? route('portal.custom.audio', [$attempt, $item]) : null, 'imageUrl' => fn ($item) => $item->image_path ? route('portal.custom.image', [$attempt, $item]) : null])

    <form method="POST" action="{{ route('portal.custom.submit', $attempt) }}" id="submit-form">@csrf
        <input type="hidden" name="answers" id="answers">
        <button class="btn btn-success btn-lg w-100" onclick="return confirm('Nộp bài? Đã nộp thì không sửa được.')"><i class="bi bi-send"></i> Nộp bài</button>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const saved = @json($attempt->answers ?? new stdClass);
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const collectAll = () => {
        const out = {};
        document.querySelectorAll('.custom-item').forEach((c) => { out[c.dataset.item] = QT.collect(c.querySelector('.qt')); });
        return out;
    };
    document.addEventListener('DOMContentLoaded', () => {
        Object.entries(saved).forEach(([id, a]) => { const el = document.getElementById('ci-' + id); if (el && a) QT.fill(el, a); });
    });
    document.getElementById('submit-form').addEventListener('submit', () => { document.getElementById('answers').value = JSON.stringify(collectAll()); });

    // Tự lưu nháp mỗi 20 giây
    const status = document.getElementById('autosave');
    setInterval(async () => {
        try {
            const fd = new FormData(); fd.append('answers', JSON.stringify(collectAll()));
            const r = await fetch(@json(route('portal.custom.save', $attempt)), { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin' });
            if (r.ok) status.textContent = 'Đã lưu nháp ' + new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
        } catch (e) {}
    }, 20000);

    // Ghi âm phần Nói → tải lên ngay
    document.addEventListener('qt-recorded', async (e) => {
        const card = e.target.closest('.custom-item');
        const fd = new FormData();
        fd.append('audio', e.detail.blob, 'answer.' + (e.detail.blob.type.includes('mp4') ? 'm4a' : 'webm'));
        const st = e.target.querySelector('.qt-rec-status');
        st.textContent = 'Đang lưu ghi âm…';
        try {
            const r = await fetch(@json(url('/thi-sinh/bai-lam-tuy-chinh/'.$attempt->id.'/ghi-am')) + '/' + card.dataset.item, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) throw new Error('Lỗi ' + r.status);
            st.textContent = '✓ Đã lưu ghi âm (' + e.detail.seconds + ' giây)';
        } catch (err) { st.textContent = 'Không lưu được ghi âm: ' + err.message + '. Hãy ghi lại.'; }
    });

    // Đồng hồ: hết giờ thì tự nộp
    const timer = document.getElementById('timer');
    if (timer) {
        const end = Number(timer.dataset.deadline) * 1000;
        const tick = () => {
            const left = Math.max(0, Math.round((end - Date.now()) / 1000));
            timer.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
            if (left === 0) { clearInterval(h); document.getElementById('answers').value = JSON.stringify(collectAll()); document.getElementById('submit-form').submit(); }
        };
        const h = setInterval(tick, 1000); tick();
    }
})();
</script>
@endpush
