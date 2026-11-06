/*
 * Thư viện dạng bài — mã giao diện dùng chung (trang thư viện, sau này trang làm bài của học viên).
 *  - QT.collect(el): gom câu trả lời từ các phần tử có data-a trong một khối .qt → object JSON.
 *  - QT.fill(el, answer): điền sẵn câu trả lời (xem đáp án).
 *  - QT.showResult(el, graded): tô đúng / sai từng ô theo kết quả chấm.
 *  Widget: ngân hàng từ, sắp xếp, ghép từ, sửa lỗi, gạch chân, chọn điểm trên ảnh, ghi âm, đếm từ, Hanzi Writer, máy đọc.
 */
window.QT = (function () {
    const $$ = (root, sel) => Array.from(root.querySelectorAll(sel));
    const b64 = (s) => { try { return decodeURIComponent(escape(atob(s))); } catch (e) { return ''; } };
    const fire = (el) => el.dispatchEvent(new Event('change', { bubbles: true }));

    function collect(root) {
        const out = {};
        $$(root, '[data-a]').forEach((el) => {
            const k = el.dataset.a;
            if (el.type === 'radio') {
                if (!(k in out)) out[k] = '';
                if (el.checked) out[k] = el.value;
            } else if (el.type === 'checkbox') {
                out[k] = out[k] || [];
                if (el.checked) out[k].push(el.value);
            } else if (el.dataset.json) {
                try { out[k] = el.value ? JSON.parse(el.value) : null; } catch (e) { out[k] = null; }
            } else {
                out[k] = el.value;
            }
        });
        return out;
    }

    // ------------------------------------------------------------ máy đọc
    function speakLang(el) { return (el.closest('.qt') || document.body).dataset.speech || 'en-US'; }
    function initSpeak(root) {
        $$(root, '[data-speak]').forEach((b) => b.addEventListener('click', () => {
            if (!window.AppSpeech) return alert('Trình duyệt không hỗ trợ đọc.');
            const wrap = b.closest('.qt-listen');
            const rate = b.dataset.rate ? Number(b.dataset.rate) : (wrap ? Number(wrap.querySelector('.qt-rate').value) : 0.9);
            AppSpeech.speakNow(b64(b.dataset.speak), speakLang(b), rate);
        }));
        $$(root, '[data-speak-stop]').forEach((b) => b.addEventListener('click', () => window.AppSpeech && AppSpeech.stop()));
    }

    // ------------------------------------------------------------ ngân hàng từ (kéo thả + bấm)
    function initBank(root) {
        $$(root, '.qt-bankwrap').forEach((wrap) => {
            const reuse = wrap.dataset.reuse === '1';
            let active = null;
            const slots = $$(wrap, '.qt-slot');
            const chips = $$(wrap, '.qt-chip');
            const sync = () => {
                const used = slots.map((s) => s.querySelector('input').value).filter(Boolean);
                chips.forEach((c) => { c.classList.toggle('qt-used', !reuse && used.includes(c.textContent.trim())); });
                slots.forEach((s) => s.classList.toggle('qt-active', s === active));
            };
            const put = (slot, word) => {
                const input = slot.querySelector('input');
                input.value = word; slot.querySelector('.qt-slot-text').textContent = word;
                slot.classList.toggle('qt-filled', !!word);
                fire(input);
                active = slots.find((s) => !s.querySelector('input').value) || null;
                sync();
            };
            slots.forEach((s) => {
                s.addEventListener('click', () => { if (s.querySelector('input').value) put(s, ''); active = s; sync(); });
                s.addEventListener('dragover', (e) => { e.preventDefault(); s.classList.add('qt-over'); });
                s.addEventListener('dragleave', () => s.classList.remove('qt-over'));
                s.addEventListener('drop', (e) => { e.preventDefault(); s.classList.remove('qt-over'); put(s, e.dataTransfer.getData('text/plain')); });
                s.addEventListener('qt-fill', (e) => put(s, e.detail));
            });
            chips.forEach((c) => {
                const pick = () => { if (c.classList.contains('qt-used')) return; const target = active || slots.find((s) => !s.querySelector('input').value); if (target) put(target, c.textContent.trim()); };
                c.addEventListener('click', pick);
                c.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(); } });
                c.addEventListener('dragstart', (e) => e.dataTransfer.setData('text/plain', c.textContent.trim()));
            });
            active = slots[0] || null;
            sync();
        });
    }

    // ------------------------------------------------------------ sắp xếp thứ tự
    function initOrder(root) {
        $$(root, '.qt-order').forEach((box) => {
            const list = box.querySelector('.qt-order-list');
            const input = box.querySelector('input[data-a]');
            const sync = () => { input.value = JSON.stringify($$(list, '.qt-order-item').map((li) => Number(li.dataset.idx))); fire(input); };
            let dragged = null;
            $$(list, '.qt-order-item').forEach((li) => {
                $$(li, '[data-move]').forEach((b) => b.addEventListener('click', () => {
                    if (b.dataset.move === '-1' && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
                    if (b.dataset.move === '1' && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
                    sync();
                }));
                li.addEventListener('dragstart', () => { dragged = li; li.classList.add('qt-dragging'); });
                li.addEventListener('dragend', () => { li.classList.remove('qt-dragging'); dragged = null; sync(); });
                li.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    if (!dragged || dragged === li) return;
                    const r = li.getBoundingClientRect();
                    list.insertBefore(dragged, e.clientY < r.top + r.height / 2 ? li : li.nextElementSibling);
                });
            });
            box.addEventListener('qt-fill', (e) => {
                (e.detail || []).forEach((idx) => { const li = list.querySelector('[data-idx="' + idx + '"]'); if (li) list.appendChild(li); });
                sync();
            });
            sync();
        });
    }

    // ------------------------------------------------------------ ghép từ thành câu
    function initWords(root) {
        $$(root, '.qt-words').forEach((box) => {
            const built = box.querySelector('.qt-built');
            const input = box.querySelector('input[data-a]');
            const chips = $$(box, '.qt-chip');
            const joiner = box.dataset.joiner;
            let seq = [];
            const sync = () => {
                built.innerHTML = '';
                seq.forEach((c, i) => {
                    const s = document.createElement('button'); s.type = 'button'; s.className = 'qt-chip qt-chip-on'; s.textContent = c.textContent;
                    s.addEventListener('click', () => { seq.splice(i, 1); sync(); });
                    built.appendChild(s);
                });
                chips.forEach((c) => c.classList.toggle('qt-used', seq.includes(c)));
                input.value = seq.map((c) => c.textContent.trim()).join(joiner);
                fire(input);
            };
            chips.forEach((c) => c.addEventListener('click', () => { if (!seq.includes(c)) { seq.push(c); sync(); } }));
            box.querySelector('.qt-words-reset').addEventListener('click', () => { seq = []; sync(); });
            box.addEventListener('qt-fill', (e) => {
                const words = joiner ? String(e.detail).split(joiner) : [];
                seq = [];
                const pool = chips.slice();
                if (!joiner) { // tiếng Trung: tham lam theo thẻ dài nhất khớp đầu chuỗi
                    let rest = String(e.detail);
                    while (rest) { const c = pool.filter((p) => rest.startsWith(p.textContent.trim())).sort((a, b) => b.textContent.length - a.textContent.length)[0]; if (!c) break; seq.push(c); pool.splice(pool.indexOf(c), 1); rest = rest.slice(c.textContent.trim().length); }
                } else {
                    words.forEach((w) => { const c = pool.find((p) => p.textContent.trim() === w); if (c) { seq.push(c); pool.splice(pool.indexOf(c), 1); } });
                }
                sync();
            });
        });
    }

    // ------------------------------------------------------------ tìm & sửa lỗi / gạch chân
    function initTokens(root) {
        $$(root, '.qt-err').forEach((box) => {
            const input = box.querySelector('input[data-a]');
            const fix = box.querySelector('.qt-err-fix');
            let index = null;
            const sync = () => {
                $$(box, '.qt-tok').forEach((t) => t.classList.toggle('qt-tok-on', Number(t.dataset.i) === index));
                if (fix) fix.disabled = index === null;
                input.value = index === null ? '' : JSON.stringify({ index, fix: fix ? fix.value : '' });
                fire(input);
            };
            $$(box, '.qt-tok').forEach((t) => t.addEventListener('click', () => { index = index === Number(t.dataset.i) ? null : Number(t.dataset.i); sync(); if (fix && index !== null) fix.focus(); }));
            if (fix) fix.addEventListener('input', sync);
            box.addEventListener('qt-fill', (e) => { index = e.detail ? e.detail.index : null; if (fix) fix.value = e.detail ? e.detail.fix : ''; sync(); });
        });
        $$(root, '.qt-hl').forEach((box) => {
            const input = box.querySelector('input[data-a]');
            const sel = new Set();
            const sync = () => { $$(box, '.qt-tok').forEach((t) => t.classList.toggle('qt-tok-on', sel.has(Number(t.dataset.i)))); input.value = JSON.stringify([...sel].sort((a, b) => a - b)); fire(input); };
            $$(box, '.qt-tok').forEach((t) => t.addEventListener('click', () => { const i = Number(t.dataset.i); sel.has(i) ? sel.delete(i) : sel.add(i); sync(); }));
            box.addEventListener('qt-fill', (e) => { sel.clear(); (e.detail || []).forEach((i) => sel.add(i)); sync(); });
        });
    }

    // ------------------------------------------------------------ chọn điểm trên ảnh
    function initHotspot(root) {
        $$(root, '.qt-hot').forEach((box) => {
            const fig = box.querySelector('.qt-hot-img');
            const current = () => (box.querySelector('input[type=radio]:checked') || {}).value;
            const draw = () => {
                $$(fig, '.qt-marker').forEach((m) => m.remove());
                $$(box, 'input[data-a]').forEach((inp) => {
                    const label = inp.previousElementSibling;
                    const state = label && label.querySelector('.qt-hot-state');
                    if (!inp.value) { if (state) state.textContent = '— chưa chọn'; return; }
                    const p = JSON.parse(inp.value);
                    const m = document.createElement('span'); m.className = 'qt-marker qt-marker-pin'; m.textContent = inp.dataset.a;
                    m.style.left = p.x + '%'; m.style.top = p.y + '%';
                    fig.appendChild(m);
                    if (state) state.textContent = '— đã chọn';
                });
            };
            fig.addEventListener('click', (e) => {
                const id = current(); if (!id) return;
                const r = fig.getBoundingClientRect();
                const p = { x: Math.round((e.clientX - r.left) / r.width * 1000) / 10, y: Math.round((e.clientY - r.top) / r.height * 1000) / 10 };
                const inp = box.querySelector('input[data-a="' + CSS.escape(id) + '"]');
                inp.value = JSON.stringify(p); fire(inp);
                const radios = $$(box, 'input[type=radio]'); const k = radios.findIndex((x) => x.checked);
                if (radios[k + 1]) radios[k + 1].checked = true;
                draw();
            });
            box.addEventListener('qt-redraw', draw);
        });
    }

    // ------------------------------------------------------------ viết: đếm từ + đồng hồ
    function initWriting(root) {
        $$(root, '.qt-writing').forEach((box) => {
            const t = box.querySelector('textarea'); const out = box.querySelector('.qt-wc');
            const count = () => { const v = t.value.trim(); out.textContent = box.dataset.lang === 'zh' ? (v.match(/\p{Script=Han}/gu) || []).length : (v ? v.split(/\s+/).length : 0); };
            t.addEventListener('input', count); t.addEventListener('qt-filled', count);
            const timer = box.querySelector('.qt-timer');
            if (timer) {
                let left = Number(box.dataset.minutes) * 60, started = false;
                t.addEventListener('focus', () => {
                    if (started) return; started = true;
                    const h = setInterval(() => { left--; timer.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0'); if (left <= 0) { clearInterval(h); timer.textContent = 'Hết giờ'; timer.classList.add('qt-bad'); } }, 1000);
                });
            }
        });
    }

    // ------------------------------------------------------------ nói: chuẩn bị + ghi âm (lưu trên máy, chưa tải lên)
    function initSpeaking(root) {
        $$(root, '.qt-speak').forEach((box) => {
            const rec = box.querySelector('.qt-rec'), stop = box.querySelector('.qt-stop'), play = box.querySelector('.qt-play');
            const status = box.querySelector('.qt-rec-status'), sec = box.querySelector('.qt-sec'), prep = box.querySelector('.qt-prep');
            const recorded = box.querySelector('[data-a=recorded]'), seconds = box.querySelector('[data-a=seconds]');
            const max = Number(box.dataset.speakMax) || 60;
            let mr, chunks = [], t0, timer, stream;
            if (prep) prep.addEventListener('click', () => {
                let left = Number(box.dataset.prep); prep.disabled = true;
                const h = setInterval(() => { left--; prep.textContent = '⏱ Còn ' + left + ' giây chuẩn bị'; if (left <= 0) { clearInterval(h); prep.textContent = '⏱ Hết giờ chuẩn bị'; rec.click(); } }, 1000);
            });
            rec.addEventListener('click', async () => {
                if (!navigator.mediaDevices || !window.MediaRecorder) return alert('Trình duyệt không hỗ trợ ghi âm.');
                try { stream = await navigator.mediaDevices.getUserMedia({ audio: true }); } catch (e) { return alert('Không truy cập được micro. Hãy cho phép dùng micro.'); }
                chunks = []; mr = new MediaRecorder(stream);
                mr.ondataavailable = (e) => e.data.size && chunks.push(e.data);
                mr.onstop = () => {
                    clearInterval(timer); stream.getTracks().forEach((tr) => tr.stop());
                    const dur = Math.round((Date.now() - t0) / 1000);
                    play.src = URL.createObjectURL(new Blob(chunks, { type: mr.mimeType || 'audio/webm' })); play.hidden = false;
                    rec.hidden = false; stop.hidden = true; rec.textContent = '↺ Ghi lại';
                    recorded.value = '1'; seconds.value = dur; fire(recorded);
                    status.textContent = '✓ Đã ghi âm ' + dur + ' giây';
                    box.dispatchEvent(new CustomEvent('qt-recorded', { bubbles: true, detail: { blob: new Blob(chunks, { type: mr.mimeType || 'audio/webm' }), seconds: dur } }));
                };
                mr.start(); t0 = Date.now(); rec.hidden = true; stop.hidden = false;
                timer = setInterval(() => { const s = Math.round((Date.now() - t0) / 1000); sec.textContent = s; if (s >= max) mr.stop(); }, 500);
            });
            stop.addEventListener('click', () => mr && mr.state === 'recording' && mr.stop());
        });
    }

    // ------------------------------------------------------------ chữ Hán (Hanzi Writer)
    function initHanzi(root) {
        if (!window.HanziWriter) return;
        $$(root, '.qt-hanzi').forEach((box) => {
            const writers = [];
            $$(box, '.qt-hanzi-cell').forEach((cell) => {
                const el = cell.querySelector('.qt-hanzi-box'), state = cell.querySelector('.qt-hanzi-state'), input = cell.querySelector('input');
                const w = HanziWriter.create(el, el.dataset.char, { width: 140, height: 140, padding: 8, showOutline: true, showCharacter: false, showHintAfterMisses: 3, highlightOnComplete: true });
                writers.push(w);
                w.quiz({ onComplete: (s) => { input.value = s.totalMistakes; fire(input); state.textContent = '✓ Xong — ' + s.totalMistakes + ' nét sai'; } });
            });
            box.querySelector('.qt-hanzi-demo').addEventListener('click', () => {
                writers.reduce((p, w) => p.then(() => new Promise((r) => w.animateCharacter({ onComplete: r }))), Promise.resolve())
                    .then(() => writers.forEach((w, i) => w.quiz({ onComplete: (s) => { const cell = box.querySelectorAll('.qt-hanzi-cell')[i]; cell.querySelector('input').value = s.totalMistakes; cell.querySelector('.qt-hanzi-state').textContent = '✓ Xong — ' + s.totalMistakes + ' nét sai'; } })));
            });
        });
    }

    // ------------------------------------------------------------ điền sẵn đáp án / tô kết quả
    function fill(root, answer) {
        Object.entries(answer || {}).forEach(([k, v]) => {
            const els = $$(root, '[data-a="' + CSS.escape(k) + '"]');
            els.forEach((el) => {
                if (el.type === 'radio') el.checked = el.value === String(v);
                else if (el.type === 'checkbox') el.checked = Array.isArray(v) && v.includes(el.value);
                else if (el.closest('.qt-slot')) el.closest('.qt-slot').dispatchEvent(new CustomEvent('qt-fill', { detail: v }));
                else if (el.closest('.qt-order')) el.closest('.qt-order').dispatchEvent(new CustomEvent('qt-fill', { detail: v }));
                else if (el.closest('.qt-words')) el.closest('.qt-words').dispatchEvent(new CustomEvent('qt-fill', { detail: v }));
                else if (el.closest('.qt-err')) el.closest('.qt-err').dispatchEvent(new CustomEvent('qt-fill', { detail: v }));
                else if (el.closest('.qt-hl')) el.closest('.qt-hl').dispatchEvent(new CustomEvent('qt-fill', { detail: v }));
                else { el.value = el.dataset.json ? JSON.stringify(v) : (v ?? ''); el.dispatchEvent(new Event('qt-filled')); }
            });
        });
        $$(root, '.qt-hot').forEach((b) => b.dispatchEvent(new Event('qt-redraw')));
        $$(root, '.qt-writing textarea').forEach((t) => t.dispatchEvent(new Event('input')));
    }

    function showResult(root, graded) {
        $$(root, '.qt-ok, .qt-bad').forEach((el) => el.classList.remove('qt-ok', 'qt-bad'));
        (graded.parts || []).forEach((p) => {
            const els = $$(root, '[data-a="' + CSS.escape(p.id) + '"]');
            els.forEach((el) => {
                const target = el.closest('.qt-gap, .qt-slot, .qt-match-row, .qt-q, .qt-cat-row') || el;
                if (!graded.pending) target.classList.add(p.correct ? 'qt-ok' : 'qt-bad');
            });
        });
    }

    function init(root) {
        root = root || document;
        initSpeak(root); initBank(root); initOrder(root); initWords(root); initTokens(root);
        initHotspot(root); initWriting(root); initSpeaking(root); initHanzi(root);
        // Giới hạn số ô được tích ở câu nhiều đáp án.
        $$(root, '[data-choose]').forEach((q) => q.addEventListener('change', () => {
            const boxes = $$(q, 'input[type=checkbox]'); const n = boxes.filter((b) => b.checked).length;
            boxes.forEach((b) => { b.disabled = !b.checked && n >= Number(q.dataset.choose); });
        }));
    }

    document.addEventListener('DOMContentLoaded', () => init(document));

    return { collect, fill, showResult, init };
})();
