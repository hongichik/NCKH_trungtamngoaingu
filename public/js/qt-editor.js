/*
 * Trình soạn bài tập dạng form (không cần sửa JSON). Mỗi .qte có:
 *   data-engine, data-options (tuỳ chọn của dạng bài, vd choices cố định), data-content (JSON hiện tại)
 *   và một <textarea data-qte-json> (ô JSON gửi lên máy chủ). Mở "JSON nâng cao" thì ô JSON là bản gốc.
 * Giữ nguyên các trường form không quản lý (ảnh, điểm trên bản đồ, language…).
 */
window.QTE = (function () {
    const el = (tag, attrs = {}, ...kids) => {
        const e = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (k === 'class') e.className = v;
            else if (k === 'text') e.textContent = v;
            else if (k.startsWith('on')) e.addEventListener(k.slice(2), v);
            else if (v !== null && v !== undefined && v !== false) e.setAttribute(k, v === true ? '' : v);
        }
        kids.flat().forEach((c) => c !== null && c !== undefined && e.append(c.nodeType ? c : document.createTextNode(c)));
        return e;
    };
    const letter = (i) => String.fromCharCode(65 + i);
    const lines = (s) => String(s || '').split('\n').map((x) => x.trim()).filter(Boolean);
    const semi = (s) => String(s || '').split(';').map((x) => x.trim()).filter(Boolean);
    const optText = (o) => (o && typeof o === 'object' ? o.text : o) ?? '';
    const gapIds = (text) => [...String(text || '').matchAll(/\[\[([A-Za-z0-9_-]+)\]\]/g)].map((m) => m[1]).filter((v, i, a) => a.indexOf(v) === i);

    // ---------------------------------------------------------------- ô nhập
    function field(label, input, hint) {
        return el('div', { class: 'mb-2' }, el('label', { class: 'form-label small mb-1', text: label }), input, hint ? el('div', { class: 'form-hint', text: hint }) : null);
    }
    function input(value, onchange, attrs = {}) {
        return el('input', Object.assign({ class: 'form-control form-control-sm', value: value ?? '', oninput: (e) => onchange(e.target.value) }, attrs));
    }
    function area(value, onchange, rows = 3, attrs = {}) {
        const t = el('textarea', Object.assign({ class: 'form-control form-control-sm', rows, oninput: (e) => onchange(e.target.value) }, attrs));
        t.value = value ?? '';
        return t;
    }
    function select(options, value, onchange) {
        const s = el('select', { class: 'form-select form-select-sm', onchange: (e) => onchange(e.target.value) }, el('option', { value: '', text: '— chọn —' }));
        options.forEach(([v, l]) => s.append(el('option', { value: v, text: l, selected: String(v) === String(value) })));
        return s;
    }
    /** Nút chèn ô trống [[n]] tại con trỏ của textarea. */
    function gapButton(textarea, onchange) {
        return el('button', { type: 'button', class: 'btn btn-sm btn-outline-primary mt-1', text: '+ Chèn ô trống tại con trỏ', onclick: () => {
            const ids = gapIds(textarea.value).map((x) => parseInt(x, 10)).filter((x) => !isNaN(x));
            const tag = '[[' + ((ids.length ? Math.max(...ids) : 0) + 1) + ']]';
            const a = textarea.selectionStart ?? textarea.value.length, b = textarea.selectionEnd ?? a;
            textarea.value = textarea.value.slice(0, a) + tag + textarea.value.slice(b);
            onchange(textarea.value, true);
        } });
    }
    /** Nút bọc chữ đang bôi đen bằng *…* (đánh dấu chỗ sai / từ cần chọn). */
    function markButton(textarea, label, onchange) {
        return el('button', { type: 'button', class: 'btn btn-sm btn-outline-primary mt-1', text: label, onclick: () => {
            const a = textarea.selectionStart, b = textarea.selectionEnd;
            if (a === b) return alert('Bôi đen từ cần đánh dấu trước.');
            textarea.value = textarea.value.slice(0, a) + '*' + textarea.value.slice(a, b).replace(/\*/g, '') + '*' + textarea.value.slice(b);
            onchange(textarea.value);
        } });
    }
    /** Danh sách có thể thêm / xoá / đổi thứ tự. render(item, index, update) → node. */
    function list(items, makeNew, render, onchange, addLabel) {
        const wrap = el('div');
        const draw = () => {
            wrap.innerHTML = '';
            items.forEach((it, i) => {
                const card = el('div', { class: 'border rounded p-2 mb-2 bg-light-subtle position-relative' });
                const tools = el('div', { class: 'd-flex gap-1 justify-content-end mb-1' },
                    el('span', { class: 'badge bg-secondary-lt me-auto', text: '#' + (i + 1) }),
                    el('button', { type: 'button', class: 'btn btn-sm btn-ghost-secondary py-0', text: '▲', disabled: i === 0, onclick: () => { [items[i - 1], items[i]] = [items[i], items[i - 1]]; onchange(); draw(); } }),
                    el('button', { type: 'button', class: 'btn btn-sm btn-ghost-secondary py-0', text: '▼', disabled: i === items.length - 1, onclick: () => { [items[i + 1], items[i]] = [items[i], items[i + 1]]; onchange(); draw(); } }),
                    el('button', { type: 'button', class: 'btn btn-sm btn-ghost-danger py-0', text: '✕', onclick: () => { items.splice(i, 1); onchange(); draw(); } }));
                card.append(tools, render(it, i, () => { onchange(); }, draw));
                wrap.append(card);
            });
            wrap.append(el('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', text: addLabel || '+ Thêm', onclick: () => { items.push(makeNew(items.length)); onchange(); draw(); } }));
        };
        draw();
        return wrap;
    }
    const nextId = (arr) => String(arr.reduce((m, q) => Math.max(m, parseInt(q.id, 10) || 0), 0) + 1);

    // ---------------------------------------------------------------- ngữ liệu chung
    function stimulusFields(c, save) {
        c.stimulus = c.stimulus || {};
        const s = c.stimulus;
        const box = el('details', { class: 'mb-2', open: !!(s.passage || s.transcript || s.title) },
            el('summary', { class: 'small fw-semibold', text: 'Ngữ liệu: bài đọc / lời bài nghe (nếu có)' }));
        box.append(
            field('Tiêu đề', input(s.title, (v) => { s.title = v; save(); })),
            field('Bài đọc / đoạn văn', area(s.passage, (v) => { s.passage = v; save(); }, 6)),
            field('Lời bài nghe (máy đọc khi chưa có file nghe)', area(s.transcript, (v) => { s.transcript = v; save(); }, 3)),
        );
        return box;
    }

    // ---------------------------------------------------------------- từng engine
    const E = {};

    E.choice = (c, save, opt) => {
        c.questions = c.questions || [];
        const fixed = opt.choices ? Object.entries(opt.choices) : null;
        return list(c.questions, (n) => ({ id: nextId(c.questions), prompt: '', ...(fixed ? {} : { options: ['', '', ''] }), answer: fixed ? fixed[0][0] : 'A' }), (q, i, upd, redraw) => {
            const box = el('div');
            box.append(el('div', { class: 'row g-2' },
                el('div', { class: 'col-3' }, field('Số câu', input(q.id, (v) => { q.id = v; upd(); }))),
                el('div', { class: 'col-9' }, field('Câu hỏi', area(q.prompt, (v) => { q.prompt = v; upd(); }, 2)))));
            if (fixed) {
                box.append(field('Đáp án', select(fixed, q.answer, (v) => { q.answer = v; upd(); })));
            } else {
                q.options = (q.options || []).map(optText);
                const opts = el('div');
                const group = 'qte-' + Math.random().toString(36).slice(2);
                const drawOpts = () => {
                    opts.innerHTML = '';
                    q.options.forEach((o, j) => opts.append(el('div', { class: 'input-group input-group-sm mb-1' },
                        el('label', { class: 'input-group-text', title: 'Đáp án đúng' }, el('input', { type: 'radio', name: group, class: 'form-check-input m-0', checked: q.answer === letter(j), onchange: () => { q.answer = letter(j); upd(); } }), ' ' + letter(j)),
                        input(o, (v) => { q.options[j] = v; upd(); }),
                        el('button', { type: 'button', class: 'btn btn-outline-danger', text: '✕', onclick: () => { q.options.splice(j, 1); if (q.answer >= letter(q.options.length)) q.answer = 'A'; upd(); drawOpts(); } }))));
                    opts.append(el('button', { type: 'button', class: 'btn btn-sm btn-ghost-primary', text: '+ Lựa chọn', onclick: () => { q.options.push(''); upd(); drawOpts(); } }));
                };
                drawOpts();
                box.append(field('Lựa chọn (bấm ô tròn = đáp án đúng)', opts));
            }
            box.append(field('Giải thích (tuỳ chọn)', input(q.explanation, (v) => { q.explanation = v; upd(); })));
            return box;
        }, save, '+ Thêm câu hỏi');
    };

    E.multi_choice = (c, save) => {
        c.questions = c.questions || [];
        return list(c.questions, () => ({ id: nextId(c.questions), prompt: '', options: ['', '', '', ''], answers: [] }), (q, i, upd) => {
            q.options = (q.options || []).map(optText);
            const box = el('div', {}, field('Câu hỏi', area(q.prompt, (v) => { q.prompt = v; upd(); }, 2)));
            const opts = el('div');
            const drawOpts = () => {
                opts.innerHTML = '';
                q.options.forEach((o, j) => opts.append(el('div', { class: 'input-group input-group-sm mb-1' },
                    el('label', { class: 'input-group-text' }, el('input', { type: 'checkbox', class: 'form-check-input m-0', checked: (q.answers || []).includes(letter(j)), onchange: (e) => {
                        q.answers = (q.answers || []).filter((a) => a !== letter(j)); if (e.target.checked) q.answers.push(letter(j)); q.answers.sort(); upd(); } }), ' ' + letter(j)),
                    input(o, (v) => { q.options[j] = v; upd(); }),
                    el('button', { type: 'button', class: 'btn btn-outline-danger', text: '✕', onclick: () => { q.options.splice(j, 1); q.answers = []; upd(); drawOpts(); } }))));
                opts.append(el('button', { type: 'button', class: 'btn btn-sm btn-ghost-primary', text: '+ Lựa chọn', onclick: () => { q.options.push(''); upd(); drawOpts(); } }));
            };
            drawOpts();
            box.append(field('Lựa chọn (tích các đáp án đúng)', opts));
            return box;
        }, save, '+ Thêm câu hỏi');
    };

    /** Đoạn văn có ô trống + danh sách ô (dùng cho 3 dạng điền từ). renderGap(id, holder) vẽ phần đáp án của 1 ô. */
    function gapEditor(c, save, renderGap, extraTop) {
        const gaps = el('div');
        const drawGaps = () => {
            gaps.innerHTML = '';
            const ids = gapIds(c.text);
            if (!ids.length) gaps.append(el('div', { class: 'text-muted small', text: 'Chưa có ô trống. Đặt con trỏ vào đoạn văn rồi bấm "Chèn ô trống".' }));
            ids.forEach((id) => { const row = el('div', { class: 'd-flex gap-2 align-items-start mb-1' }, el('span', { class: 'badge bg-blue-lt mt-1', text: 'Ô ' + id })); const h = el('div', { class: 'flex-fill' }); renderGap(id, h); row.append(h); gaps.append(row); });
        };
        const t = area(c.text, (v) => { c.text = v; save(); clearTimeout(t._tm); t._tm = setTimeout(drawGaps, 400); }, 6);
        const box = el('div');
        if (extraTop) box.append(extraTop);
        box.append(field('Đoạn văn (ô trống viết [[1]], [[2]]…; dòng có dấu "|" hiển thị thành bảng)', t),
            gapButton(t, (v) => { c.text = v; save(); drawGaps(); }),
            el('div', { class: 'mt-2 fw-semibold small', text: 'Đáp án từng ô' }), gaps);
        drawGaps();
        return box;
    }

    E.gap_text = (c, save) => {
        c.answers = c.answers || {};
        return gapEditor(c, save, (id, h) => h.append(input((Array.isArray(c.answers[id]) ? c.answers[id] : [c.answers[id] || '']).join('; '),
            (v) => { c.answers[id] = semi(v); save(); }, { placeholder: 'Đáp án; cách viết khác cách nhau bằng dấu ;' })),
        field('Giới hạn số từ mỗi ô (để trống = theo dạng bài)', input(c.max_words, (v) => { if (v === '') delete c.max_words; else c.max_words = parseInt(v, 10) || undefined; save(); }, { type: 'number', min: 1 })));
    };

    E.gap_select = (c, save) => {
        const shared = Array.isArray(c.options);
        if (shared) {
            c.answers = c.answers || {};
            const optsArea = area((c.options || []).map(optText).join('\n'), (v) => { c.options = lines(v); save(); }, 5);
            return gapEditor(c, save, (id, h) => h.append(select((c.options || []).map((o, j) => [letter(j), letter(j) + '. ' + optText(o)]), c.answers[id], (v) => { c.answers[id] = v; save(); })),
                field('Danh sách câu / từ dùng chung cho mọi ô (mỗi dòng một lựa chọn: A, B, C…)', optsArea));
        }
        c.gaps = c.gaps || {};
        return gapEditor(c, save, (id, h) => {
            const g = c.gaps[id] = c.gaps[id] || { options: [], answer: '' };
            const ans = el('div');
            const drawAns = () => { ans.innerHTML = ''; ans.append(select(g.options.map((o) => [o, o]), g.answer, (v) => { g.answer = v; save(); })); };
            h.append(el('div', { class: 'row g-1' },
                el('div', { class: 'col-8' }, input(g.options.join('; '), (v) => { g.options = semi(v); if (!g.options.includes(g.answer)) g.answer = ''; save(); drawAns(); }, { placeholder: 'Các lựa chọn, cách nhau bằng ;' })),
                el('div', { class: 'col-4' }, ans)));
            drawAns();
        });
    };

    E.gap_bank = (c, save) => {
        c.answers = c.answers || {};
        c.bank = c.bank || [];
        const selects = [];
        const box = gapEditor(c, save, (id, h) => { const holder = el('div'); selects.push([id, holder]); h.append(holder); drawSel(id, holder); },
            field('Ngân hàng từ (mỗi dòng một từ, có thể thêm từ gây nhiễu)', area(c.bank.join('\n'), (v) => { c.bank = lines(v); save(); selects.forEach(([id, h]) => drawSel(id, h)); }, 4)));
        function drawSel(id, holder) { holder.innerHTML = ''; holder.append(select(c.bank.map((w) => [w, w]), c.answers[id], (v) => { c.answers[id] = v; save(); })); }
        return box;
    };

    E.matching = (c, save) => {
        c.items = c.items || [];
        c.answers = c.answers || {};
        c.options = (c.options || []).map((o, j) => (o && typeof o === 'object' ? o : { key: letter(j), text: o }));
        const answerCells = [];
        const drawAnswers = () => answerCells.forEach(([it, h]) => { h.innerHTML = ''; h.append(select(c.options.map((o) => [o.key, o.key + ' — ' + o.text]), c.answers[it.id], (v) => { c.answers[it.id] = v; save(); })); });
        const items = list(c.items, () => ({ id: nextId(c.items), text: '' }), (it) => {
            const h = el('div'); answerCells.push([it, h]);
            const row = el('div', { class: 'row g-1' },
                el('div', { class: 'col-2' }, input(it.id, (v) => { const a = c.answers[it.id]; delete c.answers[it.id]; it.id = v; c.answers[v] = a; save(); }, { title: 'Số / nhãn mục' })),
                el('div', { class: 'col-6' }, input(it.text, (v) => { it.text = v; save(); }, { placeholder: 'Mục (câu, đoạn, tranh…)' })),
                el('div', { class: 'col-4' }, h));
            setTimeout(drawAnswers);
            return row;
        }, save, '+ Thêm mục');
        const opts = list(c.options, (n) => ({ key: letter(n), text: '' }), (o) => el('div', { class: 'row g-1' },
            el('div', { class: 'col-3' }, input(o.key, (v) => { o.key = v; save(); drawAnswers(); }, { title: 'Ký hiệu (A, B… hoặc i, ii…)' })),
            el('div', { class: 'col-9' }, input(o.text, (v) => { o.text = v; save(); drawAnswers(); }, { placeholder: 'Lựa chọn' }))), () => { save(); drawAnswers(); }, '+ Thêm lựa chọn');
        return el('div', {},
            field('Tiêu đề danh sách lựa chọn (tuỳ chọn)', input(c.options_title, (v) => { c.options_title = v; save(); })),
            el('label', { class: 'form-check small mb-2' }, el('input', { type: 'checkbox', class: 'form-check-input', checked: !!c.reuse, onchange: (e) => { c.reuse = e.target.checked; save(); } }), el('span', { class: 'form-check-label', text: 'Một lựa chọn được dùng nhiều lần' })),
            el('div', { class: 'row g-3' },
                el('div', { class: 'col-lg-7' }, el('div', { class: 'fw-semibold small mb-1', text: 'Các mục — chọn đáp án ở cột phải' }), items),
                el('div', { class: 'col-lg-5' }, el('div', { class: 'fw-semibold small mb-1', text: 'Danh sách lựa chọn' }), opts)));
    };

    E.categorize = (c, save) => {
        const groups = Object.entries(c.groups || {}).map(([name, items]) => ({ name, items: items.join('\n') }));
        const sync = () => { c.groups = {}; groups.forEach((g) => { if (g.name.trim()) c.groups[g.name.trim()] = lines(g.items); }); save(); };
        return list(groups, () => ({ name: '', items: '' }), (g) => el('div', {},
            field('Tên nhóm', input(g.name, (v) => { g.name = v; sync(); })),
            field('Các mục thuộc nhóm (mỗi dòng một mục)', area(g.items, (v) => { g.items = v; sync(); }, 3))), sync, '+ Thêm nhóm');
    };

    E.ordering = (c, save) => el('div', {},
        field('Yêu cầu (tuỳ chọn)', input(c.prompt, (v) => { c.prompt = v; save(); })),
        field('Các câu / đoạn theo ĐÚNG thứ tự (mỗi dòng một mục — học viên thấy bị xáo trộn)', area((c.items || []).join('\n'), (v) => { c.items = lines(v); save(); }, 6)));

    E.word_order = (c, save) => {
        c.questions = c.questions || [];
        return list(c.questions, () => ({ id: nextId(c.questions), tokens: [] }), (q, i, upd) => el('div', {},
            field('Gợi ý / yêu cầu (tuỳ chọn)', input(q.prompt, (v) => { q.prompt = v; upd(); })),
            field('Câu đúng, các thẻ từ cách nhau bằng dấu /', input((q.tokens || []).join(' / '), (v) => { q.tokens = v.split('/').map((x) => x.trim()).filter(Boolean); upd(); }, { placeholder: 'She / often / goes / swimming' })),
            field('Câu đúng khác (mỗi dòng một câu, tuỳ chọn)', area((q.accept || []).join('\n'), (v) => { q.accept = lines(v); upd(); }, 2))), save, '+ Thêm câu');
    };

    E.error_correction = (c, save, opt) => {
        c.sentences = c.sentences || [];
        return list(c.sentences, () => ({ id: nextId(c.sentences), text: '', correction: [] }), (s, i, upd) => {
            const t = area(s.text, (v) => { s.text = v; upd(); }, 2);
            return el('div', {},
                field('Câu (bôi đen chỗ sai rồi bấm nút để đánh dấu *…*)', t), markButton(t, 'Đánh dấu chỗ sai', (v) => { s.text = v; upd(); }),
                opt.locate_only ? null : field('Sửa thành (cách viết khác cách nhau bằng ;)', input([].concat(s.correction || []).join('; '), (v) => { s.correction = semi(v); upd(); })));
        }, save, '+ Thêm câu');
    };

    E.highlight = (c, save) => {
        const t = area(c.text, (v) => { c.text = v; save(); }, 6);
        return el('div', {},
            field('Yêu cầu', input(c.prompt, (v) => { c.prompt = v; save(); }, { placeholder: 'Gạch chân tất cả động từ quá khứ' })),
            field('Đoạn văn (bôi đen từ cần chọn rồi bấm nút để đánh dấu *…*)', t), markButton(t, 'Đánh dấu từ cần chọn', (v) => { c.text = v; save(); }));
    };

    E.short_text = (c, save) => {
        c.questions = c.questions || [];
        return list(c.questions, () => ({ id: nextId(c.questions), prompt: '', answers: [] }), (q, i, upd) => el('div', {},
            el('div', { class: 'row g-2' },
                el('div', { class: 'col-3' }, field('Số câu', input(q.id, (v) => { q.id = v; upd(); }))),
                el('div', { class: 'col-9' }, field('Câu hỏi / câu gốc', area(q.prompt, (v) => { q.prompt = v; upd(); }, 2)))),
            el('div', { class: 'row g-2' },
                el('div', { class: 'col-md-6' }, field('Bắt đầu bằng (điền sẵn, tuỳ chọn)', input(q.start, (v) => { q.start = v || undefined; upd(); }))),
                el('div', { class: 'col-md-6' }, field('Từ khoá bắt buộc (tuỳ chọn)', input(q.keyword, (v) => { q.keyword = v || undefined; upd(); })))),
            field('Đáp án chấp nhận (mỗi dòng một cách viết)', area((q.answers || []).join('\n'), (v) => { q.answers = lines(v); upd(); }, 2))), save, '+ Thêm câu');
    };

    E.dictation = (c, save) => {
        c.questions = c.questions || [];
        return list(c.questions, () => ({ id: nextId(c.questions), text: '' }), (q, i, upd) => field('Câu học viên nghe và chép lại', area(q.text, (v) => { q.text = v; upd(); }, 2)), save, '+ Thêm câu');
    };

    E.writing = (c, save) => el('div', {},
        field('Đề bài', area(c.prompt, (v) => { c.prompt = v; save(); }, 4)),
        el('div', { class: 'row g-2' },
            el('div', { class: 'col-4' }, field('Số từ tối thiểu', input(c.min_words, (v) => { c.min_words = v ? parseInt(v, 10) : undefined; save(); }, { type: 'number', min: 0 }))),
            el('div', { class: 'col-4' }, field('Số từ tối đa', input(c.max_words, (v) => { c.max_words = v ? parseInt(v, 10) : undefined; save(); }, { type: 'number', min: 0 }))),
            el('div', { class: 'col-4' }, field('Thời gian (phút)', input(c.time_minutes, (v) => { c.time_minutes = v ? parseInt(v, 10) : undefined; save(); }, { type: 'number', min: 0 })))),
        field('Gợi ý ý tưởng (mỗi dòng một ý)', area((c.ideas || []).join('\n'), (v) => { c.ideas = lines(v); save(); }, 3)),
        field('Từ / cụm bắt buộc dùng (mỗi dòng một từ)', area((c.keywords || []).join('\n'), (v) => { c.keywords = lines(v); save(); }, 2)),
        field('Bài mẫu (chỉ hiện sau khi nộp)', area(c.sample_answer, (v) => { c.sample_answer = v; save(); }, 4)));

    E.speaking = (c, save) => {
        const cue = c.cue_card || { topic: '', points: [] };
        return el('div', {},
            field('Yêu cầu / câu hỏi chính', area(c.prompt, (v) => { c.prompt = v; save(); }, 2)),
            field('Đoạn cần đọc to (tuỳ chọn)', area(c.read_text, (v) => { c.read_text = v || undefined; save(); }, 2)),
            field('Đoạn nghe rồi nói lại (máy đọc, không hiện chữ — tuỳ chọn)', area(c.repeat_text, (v) => { c.repeat_text = v || undefined; save(); }, 2)),
            field('Danh sách câu hỏi (mỗi dòng một câu, tuỳ chọn)', area((c.questions || []).join('\n'), (v) => { c.questions = lines(v); if (!c.questions.length) delete c.questions; save(); }, 3)),
            field('Thẻ chủ đề — chủ đề (tuỳ chọn)', input(cue.topic, (v) => { cue.topic = v; c.cue_card = cue.topic ? cue : undefined; save(); })),
            field('Thẻ chủ đề — các ý cần nói (mỗi dòng một ý)', area((cue.points || []).join('\n'), (v) => { cue.points = lines(v); c.cue_card = cue.topic ? cue : undefined; save(); }, 3)),
            el('div', { class: 'row g-2' },
                el('div', { class: 'col-6' }, field('Thời gian chuẩn bị (giây)', input(c.prep_seconds, (v) => { c.prep_seconds = v ? parseInt(v, 10) : undefined; save(); }, { type: 'number', min: 0 }))),
                el('div', { class: 'col-6' }, field('Thời gian nói tối đa (giây)', input(c.speak_seconds, (v) => { c.speak_seconds = v ? parseInt(v, 10) : undefined; save(); }, { type: 'number', min: 5 })))),
            field('Bài nói mẫu (chỉ hiện sau khi nộp)', area(c.sample_answer, (v) => { c.sample_answer = v; save(); }, 3)));
    };

    E.hanzi = (c, save) => el('div', {},
        field('Chữ Hán cần viết', input(c.characters, (v) => { c.characters = v; save(); }, { placeholder: '你好' })),
        field('Pinyin', input(c.pinyin, (v) => { c.pinyin = v; save(); })),
        field('Nghĩa', input(c.meaning, (v) => { c.meaning = v; save(); })));

    // ---------------------------------------------------------------- dọn & gắn
    /** Bỏ trường rỗng / undefined để JSON gọn (máy chủ vẫn kiểm tra theo lược đồ). */
    function clean(v) {
        if (Array.isArray(v)) return v.map(clean);
        if (v && typeof v === 'object') {
            const o = {};
            for (const [k, x] of Object.entries(v)) {
                if (x === undefined || x === null || x === '') continue;
                if (k === 'stimulus' && typeof x === 'object' && !Object.values(x).some((y) => y !== '' && y !== undefined && !(Array.isArray(y) && !y.length))) continue;
                o[k] = clean(x);
            }
            return o;
        }
        return v;
    }

    function mount(root) {
        const engine = root.dataset.engine;
        const opt = JSON.parse(root.dataset.options || '{}');
        const json = root.querySelector('[data-qte-json]');
        const adv = root.querySelector('[data-qte-advanced]');
        const host = root.querySelector('[data-qte-form]');
        let c;
        try { c = JSON.parse(json.value || '{}'); } catch (e) { c = {}; }
        if (!E[engine]) { host.innerHTML = '<div class="text-muted small">Dạng bài này sửa bằng JSON (mục Nâng cao).</div>'; if (adv) adv.open = true; return; }
        const save = () => { json.value = JSON.stringify(clean(c), null, 2); };
        host.append(field('Hướng dẫn làm bài (hiện đầu bài)', input(c.instructions, (v) => { c.instructions = v; save(); })));
        if (!['writing', 'speaking', 'hanzi', 'highlight'].includes(engine)) host.append(stimulusFields(c, save));
        host.append(E[engine](c, save, opt));
        save();
        // Mở JSON nâng cao: ô JSON thành bản gốc (sửa tay); đóng lại thì vẽ lại form từ JSON.
        if (adv && !root._qteBound) root._qteBound = true, adv.addEventListener('toggle', () => {
            if (!adv.open) { try { c = JSON.parse(json.value || '{}'); } catch (e) { alert('JSON không hợp lệ — sửa lại trước khi đóng mục Nâng cao.'); adv.open = true; return; } host.innerHTML = ''; mount._rebuild(root, c); }
        });
    }
    mount._rebuild = (root, c) => { root.querySelector('[data-qte-json]').value = JSON.stringify(c, null, 2); mount(root); };

    document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.qte').forEach(mount));
    return { mount };
})();
