{{-- Mã JS dùng chung cho trang làm bài (sắp xếp, đếm từ, ghi âm, trình phát). Biến: $speechLang --}}
<script src="{{ asset('js/speech.js') }}?v=2"></script>
<script>
// Sắp xếp câu
document.querySelectorAll('.ordering').forEach((box) => {
    const input = box.querySelector('input[type=text]');
    const buttons = box.querySelectorAll('[data-letter]');
    const sync = () => { const used = input.value.split('-').filter(Boolean); buttons.forEach((b) => { const on = used.includes(b.dataset.letter); b.disabled = on; b.classList.toggle('btn-secondary', on); b.classList.toggle('btn-outline-secondary', !on); }); };
    buttons.forEach((b) => b.addEventListener('click', () => {
        if (b.disabled) return;
        input.value = (input.value ? input.value + '-' : '') + b.dataset.letter;
        sync();
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }));
    box.querySelector('.ordering-reset').addEventListener('click', () => { input.value = ''; sync(); input.dispatchEvent(new Event('change', { bubbles: true })); });
    input.addEventListener('restore', sync);
    sync();
});

// Đếm từ đoạn văn
document.querySelectorAll('.word-count').forEach((t) => {
    const out = t.parentElement.querySelector('.wc');
    const count = () => {
        const v = t.value.trim();
        out.textContent = t.dataset.lang === 'zh' ? (v.match(/\p{Script=Han}/gu) || []).length : (v ? v.split(/\s+/).length : 0);
    };
    t.addEventListener('input', count);
    t.addEventListener('restore', count);
    count();
});

// Nghe mẫu câu đọc to
document.querySelectorAll('.sample-say').forEach((b) => b.addEventListener('click', () => AppSpeech.speakNow(b.dataset.text, @json($speechLang), 0.85)));

// Ghi âm bài Nói (MediaRecorder), tải lên ngay sau khi dừng
document.querySelectorAll('.recorder').forEach((box) => {
    const start = box.querySelector('.rec-start'), stop = box.querySelector('.rec-stop'), play = box.querySelector('.rec-play');
    const status = box.querySelector('.rec-status'), time = box.querySelector('.rec-time');
    let rec, chunks = [], t0, timer, stream;
    const csrf = document.querySelector('input[name=_token]').value;

    start.addEventListener('click', async () => {
        if (!navigator.mediaDevices || !window.MediaRecorder) return alert('Trình duyệt không hỗ trợ ghi âm. Hãy dùng Chrome, Edge hoặc Safari mới.');
        try { stream = await navigator.mediaDevices.getUserMedia({ audio: true }); } catch (e) { return alert('Không truy cập được micro. Hãy cho phép dùng micro.'); }
        chunks = [];
        rec = new MediaRecorder(stream);
        rec.ondataavailable = (e) => e.data.size && chunks.push(e.data);
        rec.onstop = async () => {
            clearInterval(timer);
            stream.getTracks().forEach((tr) => tr.stop());
            const duration = (Date.now() - t0) / 1000;
            const blob = new Blob(chunks, { type: rec.mimeType || 'audio/webm' });
            play.src = URL.createObjectURL(blob);
            play.classList.remove('d-none');
            start.classList.remove('d-none'); stop.classList.add('d-none');
            start.innerHTML = '<i class="bi bi-arrow-repeat"></i> Ghi lại';
            status.className = 'small rec-status text-muted'; status.textContent = 'Đang lưu…';
            const fd = new FormData();
            fd.append('audio', blob, 'answer.' + ((rec.mimeType || '').includes('mp4') ? 'm4a' : 'webm'));
            fd.append('duration', duration.toFixed(1));
            try {
                const r = await fetch(box.dataset.url, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin' });
                const j = await r.json().catch(() => ({}));
                if (!r.ok || j.ok === false) throw new Error(j.message || (j.errors ? Object.values(j.errors).flat().join(' ') : 'Lỗi ' + r.status));
                status.className = 'small rec-status text-success'; status.textContent = '✓ Đã lưu ghi âm (' + Math.round(duration) + 's)';
                box.dataset.recorded = '1';
                box.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (e) {
                status.className = 'small rec-status text-danger'; status.textContent = 'Không lưu được: ' + e.message;
            }
        };
        rec.start();
        t0 = Date.now();
        start.classList.add('d-none'); stop.classList.remove('d-none');
        timer = setInterval(() => {
            const s = Math.round((Date.now() - t0) / 1000);
            time.textContent = s;
            if (s >= Number(box.dataset.max)) rec.stop();
        }, 500);
    });
    stop.addEventListener('click', () => rec && rec.state === 'recording' && rec.stop());
});

// Trình phát bài Nghe (mỗi .player một trình phát)
document.querySelectorAll('.player').forEach((box) => {
    const cfg = JSON.parse(box.dataset.audio || '{}');
    const parts = box.querySelectorAll('.audio-part');
    const note = box.querySelector('.player-note');
    const setNote = (t) => { if (note) note.textContent = t; };
    const rate = () => Number(box.querySelector('.player-rate').value);
    let stopped = true, current = null;

    async function playFiles() {
        for (let i = 0; i < cfg.files.length && !stopped; i++) {
            setNote('Đang phát câu ' + (i + 1) + '/' + cfg.files.length + ' (giọng đọc do AI tạo)');
            const ok = await new Promise((resolve) => {
                current = new Audio(cfg.files[i]);
                current.playbackRate = rate();
                current.onended = () => resolve(true);
                current.onerror = () => resolve(false);
                current.play().catch(() => resolve(false));
            });
            if (!ok) { setNote('Không phát được file giọng đọc (câu ' + (i + 1) + '). Báo giáo viên kiểm tra.'); return; }
            await new Promise((r) => setTimeout(r, 350));
        }
        if (!stopped) setNote('Đã phát xong. Bấm “Nghe” để nghe lại.');
    }

    async function playBrowser() {
        if (!(await AppSpeech.hasVoice(cfg.lang))) {
            setNote('Máy chưa có giọng ' + (cfg.lang.startsWith('zh') ? 'tiếng Trung' : 'tiếng Anh') + ' — hãy dùng Chrome hoặc cài thêm giọng đọc.');
            return;
        }
        await new Promise((r) => setTimeout(r, 120)); // tránh lỗi câm của Chrome ngay sau cancel()
        for (let i = 0; i < cfg.lines.length && !stopped; i++) {
            setNote('Đang đọc câu ' + (i + 1) + '/' + cfg.lines.length + (AppSpeech.engine() === 'ai' ? ' (giọng đọc AI)' : ' (giọng của trình duyệt)'));
            const ok = await AppSpeech.say(cfg.lines[i].text, { lang: cfg.lang, rate: rate() * 0.95, gender: cfg.lines[i].voice });
            if (!ok && !stopped) { setNote('Trình duyệt không đọc được. Thử Chrome, tắt chế độ im lặng (iPhone) và tăng âm lượng.'); return; }
            await new Promise((r) => setTimeout(r, 250));
        }
        if (!stopped) setNote('Đã đọc xong. Bấm “Nghe” để nghe lại.');
    }

    box.querySelector('.player-play').addEventListener('click', async () => {
        stopped = false;
        if (cfg.mode === 'textbook') {
            for (const a of parts) {
                if (stopped) return;
                await new Promise((resolve) => { a.playbackRate = rate(); a.onended = resolve; a.onerror = resolve; current = a; a.play().catch(resolve); });
            }
        } else if (cfg.mode === 'tts') {
            await playFiles();
        } else if (AppSpeech.supported) {
            AppSpeech.stop();
            await playBrowser();
        } else {
            alert('Trình duyệt không hỗ trợ đọc. Hãy dùng Chrome hoặc Edge.');
        }
    });
    const stop = () => { stopped = true; if (current) current.pause(); AppSpeech.stop(); parts.forEach((a) => a.pause()); };
    box.querySelector('.player-stop').addEventListener('click', stop);
    box.addEventListener('player-stop', stop);
    box.querySelector('.player-rate').addEventListener('change', () => { parts.forEach((a) => { a.playbackRate = rate(); }); if (current) current.playbackRate = rate(); });
});
</script>
