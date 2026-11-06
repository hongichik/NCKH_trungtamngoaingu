/*
 * Trình phát video bài học: link ký số tự làm mới khi hết hạn, MP4 phát theo đoạn / HLS (hls.js) / Bunny (iframe),
 * watermark động (tên + email/điện thoại học viên chạy vị trí ngẫu nhiên), toàn màn hình giữ watermark,
 * chỉnh tốc độ, phụ đề, nhớ vị trí xem, báo tiến độ mỗi 10 giây.
 */
(function () {
    const csrf = () => document.querySelector('meta[name=csrf-token]').content;

    function setupWatermark(box) {
        const wm = box.querySelector('.lp-wm');
        if (!wm) return;
        const move = () => {
            wm.style.left = (5 + Math.random() * 60) + '%';
            wm.style.top = (5 + Math.random() * 75) + '%';
        };
        move();
        setInterval(move, 7000);
    }

    async function getSource(box, takeover) {
        const r = await fetch(box.dataset.source + (takeover ? '?takeover=1' : ''), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const j = await r.json().catch(() => ({}));
        if (r.status === 409) { showConflict(box, j.message); return null; }
        if (!r.ok || !j.ok) { showMessage(box, j.message || 'Không mở được video (lỗi ' + r.status + ').'); return null; }
        hideOverlay(box);
        return j;
    }

    function overlay(box) { return box.querySelector('.lp-overlay'); }
    function hideOverlay(box) { overlay(box).classList.add('d-none'); }
    function showMessage(box, msg) { const o = overlay(box); o.classList.remove('d-none'); o.innerHTML = '<div>' + msg + '</div>'; }
    function showConflict(box, msg) {
        const o = overlay(box);
        o.classList.remove('d-none');
        o.innerHTML = '<div class="mb-2">' + msg + '</div><button type="button" class="btn btn-light btn-sm">Xem ở thiết bị này</button>';
        o.querySelector('button').addEventListener('click', () => start(box, true));
    }

    async function start(box, takeover) {
        const src = await getSource(box, takeover);
        if (!src) return;
        if (src.type === 'bunny') return startBunny(box, src);
        const video = box.querySelector('video');
        video.dataset.type = src.type;
        attach(box, video, src, src.position);
        if (!box.dataset.wired) wire(box, video);
    }

    function attach(box, video, src, position) {
        if (src.type === 'hls') {
            if (window.Hls && Hls.isSupported()) {
                if (box._hls) box._hls.destroy();
                const hls = new Hls({ maxBufferLength: 30 });
                box._hls = hls;
                hls.on(Hls.Events.ERROR, (e, data) => { if (data.fatal) recover(box, video); });
                hls.loadSource(src.src);
                hls.attachMedia(video);
            } else {
                video.src = src.src; // Safari phát HLS sẵn
            }
        } else {
            video.src = src.src;
        }
        video.querySelectorAll('track').forEach((t) => t.remove());
        if (src.captions) {
            const t = document.createElement('track');
            t.kind = 'subtitles'; t.label = 'Phụ đề'; t.srclang = 'vi'; t.src = src.captions; t.default = true;
            video.appendChild(t);
        }
        if (position) video.addEventListener('loadedmetadata', () => { video.currentTime = Math.min(position, (video.duration || position) - 2); }, { once: true });
    }

    // Link hết hạn / mất mạng → xin link mới và phát tiếp từ chỗ cũ.
    async function recover(box, video) {
        if (box._recovering) return;
        box._recovering = true;
        const at = video.currentTime, playing = !video.paused;
        const src = await getSource(box, false);
        box._recovering = false;
        if (!src) return;
        attach(box, video, src, at);
        if (playing) video.addEventListener('loadedmetadata', () => video.play().catch(() => {}), { once: true });
    }

    function wire(box, video) {
        box.dataset.wired = '1';
        video.addEventListener('error', () => recover(box, video));
        video.addEventListener('contextmenu', (e) => e.preventDefault());
        const rate = box.querySelector('.lp-rate');
        if (rate) rate.addEventListener('change', () => { video.playbackRate = Number(rate.value); });
        const fs = box.querySelector('.lp-fs');
        if (fs) fs.addEventListener('click', () => {
            const el = box.querySelector('.lp-stage');
            if (document.fullscreenElement) document.exitFullscreen(); else (el.requestFullscreen || el.webkitRequestFullscreen).call(el);
        });

        // Báo tiến độ: chỉ cộng thời gian thực sự phát (không tính tua).
        let played = 0, lastT = null;
        video.addEventListener('timeupdate', () => {
            const t = video.currentTime;
            if (lastT !== null && !video.paused) { const d = t - lastT; if (d > 0 && d < 3) played += d; }
            lastT = t;
        });
        video.addEventListener('seeking', () => { lastT = null; });
        const report = async () => {
            if (played < 1 && !video.ended) return;
            const delta = Math.round(played); played = 0;
            try {
                const r = await fetch(box.dataset.progress, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ position: Math.floor(video.currentTime), delta, duration: video.duration || null }),
                });
                const j = await r.json();
                const bar = box.querySelector('.lp-progress');
                if (bar && j.ok) { bar.style.width = j.percent + '%'; bar.textContent = j.percent + '%'; }
                if (j.completed) box.querySelector('.lp-done')?.classList.remove('d-none');
                if (j.lesson_done) document.dispatchEvent(new CustomEvent('lesson-videos-done'));
            } catch (e) { /* thử lại lần sau */ }
        };
        setInterval(() => { if (!video.paused) report(); }, 10000);
        video.addEventListener('pause', report);
        video.addEventListener('ended', report);
    }

    function startBunny(box, src) {
        const stage = box.querySelector('.lp-stage');
        stage.querySelector('video')?.remove();
        let iframe = stage.querySelector('iframe');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.allow = 'accelerometer; gyroscope; encrypted-media; picture-in-picture';
            iframe.setAttribute('allowfullscreen', 'false');
            iframe.className = 'lp-iframe';
            stage.prepend(iframe);
        }
        iframe.src = src.src;
        // Tiến độ qua player.js của Bunny (nếu tải được).
        if (window.playerjs) {
            const player = new playerjs.Player(iframe);
            let last = 0, duration = 0, played = 0;
            player.on('ready', () => player.getDuration((d) => { duration = d; }));
            player.on('timeupdate', (d) => { if (d.seconds - last > 0 && d.seconds - last < 2) played += d.seconds - last; last = d.seconds; });
            setInterval(() => {
                if (played < 1) return;
                const delta = Math.round(played); played = 0;
                fetch(box.dataset.progress, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ position: Math.floor(last), delta, duration: duration || null }) });
            }, 10000);
        }
    }

    document.querySelectorAll('.lp-box').forEach((box) => {
        setupWatermark(box);
        box.querySelector('.lp-start')?.addEventListener('click', (e) => { e.currentTarget.remove(); start(box, false); });
    });
})();
