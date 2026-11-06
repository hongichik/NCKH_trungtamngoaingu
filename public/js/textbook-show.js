/*
 * Trang giáo trình: tự cập nhật tiến độ, sửa danh sách Unit (gộp / tách / thêm / xoá),
 * ước tính chi phí, tải audio theo mảnh, xem trước chữ của trang.
 */
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const root = document.getElementById('tb-show');
    if (!root) return;

    const json = async (url, opts = {}) => {
        const res = await fetch(url, { credentials: 'same-origin', ...opts, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', ...(opts.headers || {}) } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) throw new Error(data.message || ('Lỗi ' + res.status));
        return data;
    };

    // ---------------------------------------------------------------- tiến độ
    if (root.dataset.running === '1') {
        const cron = root.dataset.cron === '1';
        let lastState = null;
        const poll = async () => {
            try {
                // Chưa có cron → trang tự xử lý từng việc nền.
                if (!cron) await json(root.dataset.tickUrl, { method: 'POST' }).catch(() => {});
                const s = await json(root.dataset.statusUrl);
                const total = Object.values(s.jobs).reduce((a, b) => a + b, 0);
                const done = (s.jobs.done || 0) + (s.jobs.failed || 0);
                const jobs = document.getElementById('tb-jobs');
                if (jobs) jobs.textContent = `${done}/${total}`;
                const bar = document.getElementById('tb-jobs-bar');
                if (bar) bar.style.width = (total ? Math.round(done / total * 100) : 0) + '%';
                const state = JSON.stringify([s.status, s.units, s.audio]);
                if (lastState !== null && state !== lastState) return location.reload();
                lastState = state;
                if (!['extracting', 'ocr', 'detecting', 'processing'].includes(s.status)) return location.reload();
            } catch (e) { /* thử lại lần sau */ }
            setTimeout(poll, cron ? 5000 : 1500);
        };
        setTimeout(poll, 1500);
    }

    // ---------------------------------------------------------------- danh sách Unit
    const form = document.getElementById('units-form');
    if (form) {
        const tbody = form.querySelector('tbody');
        const field = (row, f) => row.querySelector(`[data-f="${f}"]`);

        // Đặt name[] theo thứ tự dòng trước khi gửi
        form.addEventListener('submit', () => {
            tbody.querySelectorAll('tr[data-row]').forEach((row, i) => {
                ['id', 'title', 'start_page', 'end_page', 'skipped'].forEach((f) => {
                    const el = field(row, f);
                    if (el) el.name = `units[${i}][${f}]`;
                });
                if (!field(row, 'id').value) field(row, 'id').removeAttribute('name');
            });
        });

        document.getElementById('unit-add').addEventListener('click', () => {
            const row = document.getElementById('unit-row-template').content.firstElementChild.cloneNode(true);
            const last = tbody.querySelector('tr[data-row]:last-child');
            const start = last ? Math.min(window.TB_PAGE_COUNT, Number(field(last, 'end_page').value) + 1) : 1;
            field(row, 'start_page').value = start;
            field(row, 'end_page').value = window.TB_PAGE_COUNT;
            tbody.appendChild(row);
            field(row, 'title').focus();
        });

        tbody.addEventListener('click', (ev) => {
            const btn = ev.target.closest('[data-act]');
            if (!btn) return;
            const row = btn.closest('tr');
            const act = btn.dataset.act;
            if (act === 'remove') {
                if (confirm('Xoá dòng này khỏi danh sách? (Có hiệu lực khi bấm “Lưu danh sách”)')) row.remove();
            } else if (act === 'merge') {
                const next = row.nextElementSibling;
                if (!next) return alert('Không có Unit bên dưới để gộp.');
                field(row, 'end_page').value = field(next, 'end_page').value;
                next.remove();
            } else if (act === 'split') {
                const s = Number(field(row, 'start_page').value), e = Number(field(row, 'end_page').value);
                const at = Number(prompt(`Tách tại trang nào? (Unit mới bắt đầu từ trang này, trong khoảng ${s + 1}–${e})`, Math.floor((s + e + 1) / 2)));
                if (!at || at <= s || at > e) return;
                const copy = document.getElementById('unit-row-template').content.firstElementChild.cloneNode(true);
                field(copy, 'title').value = field(row, 'title').value + ' (phần 2)';
                field(copy, 'start_page').value = at;
                field(copy, 'end_page').value = e;
                field(row, 'end_page').value = at - 1;
                row.after(copy);
            } else if (act === 'preview') {
                openPage(Number(field(row, 'start_page').value) || 1);
            }
        });

        // Chọn Unit → ước tính chi phí
        const pf = document.getElementById('process-form');
        const picks = () => Array.from(document.querySelectorAll('.unit-pick:checked')).map((c) => c.value);
        let timer;
        const estimate = () => {
            clearTimeout(timer);
            const ids = picks();
            const box = document.getElementById('estimate-box');
            const btn = document.getElementById('process-btn');
            btn.disabled = !ids.length;
            if (!ids.length) { box.textContent = 'Chọn Unit để xem ước tính…'; return; }
            box.textContent = 'Đang ước tính…';
            timer = setTimeout(async () => {
                try {
                    const q = ids.map((id) => 'units[]=' + id).join('&');
                    const r = await fetch(pf.dataset.estimateUrl + '?' + q, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then((x) => x.json());
                    box.innerHTML = `${r.units} Unit → <strong>${r.chunks} đoạn</strong>${r.audio ? ` + ${r.audio} audio (~${r.audio_minutes} phút)` : ''} · ước tính <strong>${r.calls} lượt AI, ~$${Number(r.cost_usd).toFixed(3)}</strong>`;
                } catch (e) { box.textContent = 'Không ước tính được.'; }
            }, 300);
        };
        document.querySelectorAll('.unit-pick').forEach((c) => c.addEventListener('change', estimate));
        const all = document.getElementById('unit-check-all');
        all && all.addEventListener('change', () => {
            document.querySelectorAll('.unit-pick:not(:disabled)').forEach((c) => { c.checked = all.checked; });
            estimate();
        });
        pf.addEventListener('submit', (ev) => {
            if (!confirm('Bắt đầu phân tích các Unit đã chọn bằng AI?\n' + document.getElementById('estimate-box').textContent)) ev.preventDefault();
        });
        estimate();
    }

    // ---------------------------------------------------------------- xem trước trang
    let currentPage = 1;
    const modalEl = document.getElementById('page-modal');
    async function openPage(n) {
        currentPage = Math.max(1, Math.min(window.TB_PAGE_COUNT || 1, n));
        document.getElementById('page-no').textContent = `${currentPage} / ${window.TB_PAGE_COUNT}`;
        document.getElementById('page-text').textContent = 'Đang tải…';
        if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        try {
            const r = await fetch(`${window.TB_PAGE_URL}/${currentPage}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then((x) => x.json());
            document.getElementById('page-text').textContent = r.text || (r.needs_ocr ? '(Trang scan — chưa có chữ)' : '(Trang trống)');
        } catch (e) { document.getElementById('page-text').textContent = 'Không tải được.'; }
    }
    document.getElementById('page-prev')?.addEventListener('click', () => openPage(currentPage - 1));
    document.getElementById('page-next')?.addEventListener('click', () => openPage(currentPage + 1));

    // ---------------------------------------------------------------- tải audio
    const audioInput = document.getElementById('audio-input');
    audioInput && audioInput.addEventListener('change', async () => {
        const files = Array.from(audioInput.files);
        const box = document.getElementById('audio-progress');
        const PART = 2 * 1024 * 1024;
        let ok = 0;
        for (const file of files) {
            const line = document.createElement('div');
            box.appendChild(line);
            if (file.size > 25 * 1024 * 1024) { line.innerHTML = `<span class="text-danger">✗ ${file.name}: quá 25 MB</span>`; continue; }
            const id = Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
            const total = Math.max(1, Math.ceil(file.size / PART));
            try {
                for (let part = 0; part < total; part++) {
                    line.textContent = `${file.name}: ${Math.round(part / total * 100)}%`;
                    const url = `${audioInput.dataset.url}?upload=${id}&part=${part}&total=${total}&name=${encodeURIComponent(file.name)}`;
                    let tries = 0;
                    for (;;) {
                        try { await json(url, { method: 'POST', headers: { 'Content-Type': 'application/octet-stream' }, body: file.slice(part * PART, (part + 1) * PART) }); break; } catch (e) {
                            if (++tries >= 3 || /Chỉ nhận|vượt/.test(e.message)) throw e;
                            await new Promise((r) => setTimeout(r, 1500 * tries));
                        }
                    }
                }
                line.innerHTML = `<span class="text-success">✓ ${file.name}</span>`;
                ok++;
            } catch (e) {
                line.innerHTML = `<span class="text-danger">✗ ${file.name}: ${e.message}</span>`;
            }
        }
        if (ok) setTimeout(() => location.reload(), 800);
    });
})();
