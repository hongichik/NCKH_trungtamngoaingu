/*
 * Đọc văn bản: ƯU TIÊN giọng đọc AI (GET /tts → mp3, máy chủ lưu đệm từng đoạn, đoạn đã tạo dùng lại mãi);
 * AI tắt / lỗi / hết hạn mức → quay về giọng có sẵn của trình duyệt (Web Speech API), xử lý các lỗi hay gặp:
 *  - Chrome: speak() ngay sau cancel() thường câm → chờ một chút.
 *  - Chrome: utterance bị dọn rác giữa chừng → onend không chạy, câu sau không đọc → giữ tham chiếu.
 *  - Danh sách giọng nạp chậm ở lần đầu → chờ sự kiện voiceschanged (tối đa 1,5 giây).
 *  - Chrome dừng câu dài > ~15 giây → tự "đánh thức" bằng pause/resume.
 */
window.AppSpeech = (function () {
    const browser = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
    let keep = [];

    // ---------------------------------------------------------------- giọng đọc AI
    const ttsUrl = (() => {
        const src = (document.currentScript && document.currentScript.src) || '';
        return src.includes('/js/speech.js') ? src.replace(/\/js\/speech\.js.*$/, '/tts') : '/tts';
    })();
    let aiFails = 0;                       // lỗi liên tiếp; 2 lần → dùng giọng trình duyệt cho cả trang
    const aiOn = () => aiFails < 2;
    let playing = null;                    // <audio> đang phát
    let token = 0;                         // tăng khi stop() → huỷ chuỗi đang phát

    const b64 = (str) => btoa(unescape(encodeURIComponent(str)));
    const langCode = (lang) => ((lang || 'en').toLowerCase().startsWith('zh') ? 'zh' : 'en');
    const srcOf = (text, lang, gender) => ttsUrl + '?lang=' + langCode(lang) + '&g=' + (gender === 'male' ? 'male' : 'female') + '&t=' + encodeURIComponent(b64(text));

    /** Cắt đoạn dài thành các khúc ≤ ~350 ký tự theo câu (khúc đầu ngắn để bắt đầu nghe nhanh). */
    function chunks(text) {
        const parts = String(text).replace(/\s+/g, ' ').trim().match(/[^.!?。！？;；]+[.!?。！？;；]*["'”’)]*\s*/g) || [String(text)];
        const out = [];
        let cur = '';
        for (const p of parts) {
            const limit = out.length === 0 ? 160 : 350;
            if (cur && (cur + p).length > limit) { out.push(cur.trim()); cur = ''; }
            cur += p;
            while (cur.length > 1000) { out.push(cur.slice(0, 1000)); cur = cur.slice(1000); }
        }
        if (cur.trim()) out.push(cur.trim());
        return out.filter(Boolean);
    }

    /** Giọng AI do máy chủ tạo ở tốc độ hơi chậm (≈ rate 0.9 của trình duyệt) → quy đổi tốc độ phát. */
    const playbackRate = (rate) => Math.min(1.6, Math.max(0.6, (rate || 0.9) / 0.9));

    // Một thẻ <audio> dùng chung: Safari / iPhone chỉ cho phát tiếp các khúc sau trên thẻ đã được "mở khoá" bằng cú bấm đầu.
    let player = null;
    function playOne(src, rate, my) {
        return new Promise((resolve) => {
            if (my !== token) return resolve(false);
            player = player || new Audio();
            playing = player;
            player.onended = () => resolve(true);
            player.onerror = () => resolve(null);
            player.src = src;
            player.playbackRate = playbackRate(rate);
            player.play().then(() => { player.playbackRate = playbackRate(rate); })
                .catch((e) => resolve(e && e.name === 'NotAllowedError' ? false : (e && e.name === 'AbortError' ? false : null)));
        });
    }

    /** Đọc bằng AI. true = xong; false = bị dừng; null = AI lỗi (nên quay về giọng trình duyệt). */
    async function sayAi(text, opts) {
        const my = token;
        const list = chunks(text).map((c) => srcOf(c, opts.lang, opts.gender));
        for (let i = 0; i < list.length; i++) {
            // Tải trước khúc kế tiếp trong lúc phát khúc hiện tại (máy chủ tạo + trình duyệt lưu đệm).
            if (list[i + 1]) fetch(list[i + 1], { credentials: 'same-origin' }).catch(() => {});
            const ok = await playOne(list[i], opts.rate, my);
            if (ok === null) { aiFails++; return i === 0 ? null : false; }
            if (!ok || my !== token) return false;
            aiFails = 0;
        }
        return true;
    }

    const supported = true; // luôn có ít nhất giọng AI; thiếu cả hai thì báo khi đọc

    function voices() {
        return browser ? speechSynthesis.getVoices() : [];
    }

    function waitVoices() {
        return new Promise((resolve) => {
            if (!browser || voices().length) return resolve(voices());
            const done = () => resolve(voices());
            speechSynthesis.addEventListener('voiceschanged', done, { once: true });
            setTimeout(done, 1500);
        });
    }

    function pick(lang, gender) {
        const list = voices().filter((v) => v.lang.replace('_', '-').toLowerCase().startsWith(lang.slice(0, 2).toLowerCase()));
        if (!list.length) return null;
        const exact = list.filter((v) => v.lang.replace('_', '-').toLowerCase() === lang.toLowerCase());
        const pool = exact.length ? exact : list;
        const female = pool.filter((v) => /female|woman|samantha|victoria|karen|zira|susan|serena|moira|tessa|ting|mei|xiaoxiao|huihui|yaoyao|li-mu/i.test(v.name));
        const male = pool.filter((v) => !female.includes(v));
        return (gender === 'male' ? male[0] || pool[pool.length - 1] : female[0] || pool[0]) || pool[0];
    }

    /** Có giọng cho ngôn ngữ này không. */
    async function hasVoice(lang) {
        if (aiOn()) return true;
        await waitVoices();
        return !!pick(lang, 'female');
    }

    /** Đọc 1 câu / đoạn, trả về Promise khi đọc xong (giọng AI, lỗi thì giọng trình duyệt). */
    async function say(text, opts = {}) {
        if (aiOn()) {
            const r = await sayAi(text, opts);
            if (r !== null) return r;
        }
        return sayBrowser(text, opts);
    }

    function sayBrowser(text, opts = {}) {
        return new Promise((resolve) => {
            if (!browser) return resolve(false);
            const u = new SpeechSynthesisUtterance(text);
            u.lang = opts.lang || 'en-US';
            u.rate = opts.rate || 1;
            const v = pick(u.lang, opts.gender);
            if (v) u.voice = v;
            keep.push(u); // giữ tham chiếu (lỗi Chrome)
            let wake = setInterval(() => { if (speechSynthesis.speaking && !speechSynthesis.paused) { speechSynthesis.pause(); speechSynthesis.resume(); } }, 10000);
            const finish = (ok) => { clearInterval(wake); keep = keep.filter((x) => x !== u); resolve(ok); };
            u.onend = () => finish(true);
            u.onerror = () => finish(false);
            speechSynthesis.speak(u);
        });
    }

    /** Dừng mọi thứ đang đọc. */
    function stop() {
        token++;
        if (playing) { playing.pause(); playing = null; }
        if (browser) speechSynthesis.cancel();
        keep = [];
    }

    /** Đọc 1 câu ngay (vd nút loa cạnh từ vựng). */
    async function speakNow(text, lang, rate) {
        stop();
        if (aiOn()) {
            const r = await sayAi(text, { lang, rate: rate || 0.9 });
            if (r !== null) return;
        }
        if (!browser) {
            alert('Trình duyệt này không hỗ trợ đọc. Hãy dùng Chrome, Edge hoặc Safari.');
            return;
        }
        await waitVoices();
        await new Promise((r) => setTimeout(r, 120)); // tránh lỗi câm của Chrome sau cancel()
        if (!pick(lang, 'female')) {
            alert('Máy chưa có giọng đọc ' + (lang.startsWith('zh') ? 'tiếng Trung' : 'tiếng Anh') + '. Hãy cài thêm giọng trong cài đặt ngôn ngữ của máy, hoặc dùng Chrome.');
            return;
        }
        await say(text, { lang, rate: rate || 0.9 });
    }

    /** 'ai' nếu đang dùng giọng AI, 'browser' nếu đã quay về giọng trình duyệt. */
    const engine = () => (aiOn() ? 'ai' : 'browser');

    return { supported, waitVoices, hasVoice, say, stop, speakNow, pick, engine };
})();
