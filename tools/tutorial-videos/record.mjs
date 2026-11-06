// Tự quay video hướng dẫn: Playwright lái trình duyệt theo kịch bản (scenarios/*.mjs), mỗi cảnh có lời thuyết minh
// đọc bằng giọng AI của OpenAI (giọng nam), rồi ffmpeg ghép hình + tiếng thành MP4.
//
//   cd tools/tutorial-videos && npm install
//   php artisan tutorial:demo setup          # tạo khoá học, lớp, tài khoản demo
//   node record.mjs                          # quay tất cả
//   node record.mjs giao-vien hoc-vien       # quay một số video
//
// Biến môi trường: BASE_URL (mặc định http://127.0.0.1:8000), TTS_VOICE (mặc định onyx — giọng nam), OUT_DIR.

import { chromium } from 'playwright';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '../..');
const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const OUT = process.env.OUT_DIR || path.join(ROOT, 'storage/app/public/huong-dan');
const CACHE = path.join(HERE, '.cache');
const W = 1280, H = 720;

// ---------------------------------------------------------------- cấu hình AI từ .env của dự án
function env() {
    const out = {};
    for (const line of fs.readFileSync(path.join(ROOT, '.env'), 'utf8').split('\n')) {
        const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
        if (m) out[m[1]] = m[2].replace(/^["']|["']$/g, '');
    }
    return out;
}
const ENV = env();
const OPENAI_KEY = process.env.OPENAI_API_KEY || ENV.OPENAI_API_KEY;
const OPENAI_URL = (ENV.OPENAI_BASE_URL || 'https://api.openai.com/v1').replace(/\/$/, '');
const TTS_MODEL = process.env.TTS_MODEL || ENV.AI_MODEL_TTS || 'gpt-4o-mini-tts';
const VOICE = process.env.TTS_VOICE || 'onyx';
const VOICE_STYLE = 'Giọng nam trầm, ấm, nói tiếng Việt chuẩn giọng Hà Nội, phát âm rõ ràng, tốc độ vừa phải, ngữ điệu thân thiện và tự tin như người hướng dẫn sử dụng phần mềm. '
    + 'Đọc tên tiếng Anh, tiếng Trung tự nhiên. Ngắt nghỉ ngắn ở dấu phẩy, dấu chấm.';

// ---------------------------------------------------------------- giọng đọc AI (lưu đệm theo nội dung)
async function tts(text) {
    fs.mkdirSync(path.join(CACHE, 'tts'), { recursive: true });
    const file = path.join(CACHE, 'tts', createHash('sha256').update([TTS_MODEL, VOICE, VOICE_STYLE, text].join('|')).digest('hex').slice(0, 24) + '.mp3');
    if (!fs.existsSync(file)) {
        if (!OPENAI_KEY) throw new Error('Thiếu OPENAI_API_KEY trong .env');
        for (let attempt = 1; ; attempt++) {
            const r = await fetch(OPENAI_URL + '/audio/speech', {
                method: 'POST',
                headers: { Authorization: 'Bearer ' + OPENAI_KEY, 'Content-Type': 'application/json' },
                body: JSON.stringify({ model: TTS_MODEL, voice: VOICE, input: text, instructions: VOICE_STYLE, response_format: 'mp3' }),
            });
            if (r.ok) { fs.writeFileSync(file, Buffer.from(await r.arrayBuffer())); break; }
            if (attempt >= 3) throw new Error('TTS lỗi ' + r.status + ': ' + (await r.text()).slice(0, 300));
            await sleep(2000 * attempt);
        }
    }
    return { file, duration: probe(file) };
}
const probe = (file) => parseFloat(execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', file]).toString());
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ---------------------------------------------------------------- lớp phủ trên trang: con trỏ chuột, phụ đề, thẻ tiêu đề
const OVERLAY = () => {
    if (window.__tour) return;
    window.__tour = true;
    const css = `
      #__cur{position:fixed;left:0;top:0;width:26px;height:26px;z-index:2147483647;pointer-events:none;transform:translate(-3px,-2px);transition:none}
      #__cur svg{filter:drop-shadow(0 1px 2px rgba(0,0,0,.45))}
      .__ripple{position:fixed;width:36px;height:36px;margin:-18px 0 0 -18px;border-radius:50%;border:3px solid #f59f00;z-index:2147483646;pointer-events:none;animation:__rip .6s ease-out forwards}
      @keyframes __rip{from{transform:scale(.3);opacity:1}to{transform:scale(1.8);opacity:0}}
      #__cap{position:fixed;left:50%;bottom:22px;transform:translateX(-50%);max-width:86%;z-index:2147483645;pointer-events:none;
        background:rgba(15,23,42,.86);color:#fff;font:500 19px/1.45 "Segoe UI",Roboto,Arial,sans-serif;padding:10px 20px;border-radius:12px;text-align:center;display:none}
      .__hl{outline:3px solid #f59f00 !important;outline-offset:3px !important;border-radius:6px;transition:outline-color .2s}
`;
    const mount = () => {
        if (document.getElementById('__cur')) return;
        const st = document.createElement('style'); st.textContent = css; document.documentElement.appendChild(st);
        const c = document.createElement('div'); c.id = '__cur';
        c.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24"><path d="M4 2l15 10.5-6.6 1.2 3.9 7.3-2.6 1.4-3.9-7.4L4 20z" fill="#fff" stroke="#111" stroke-width="1.4" stroke-linejoin="round"/></svg>';
        document.documentElement.appendChild(c);
        const cap = document.createElement('div'); cap.id = '__cap'; document.documentElement.appendChild(cap);
        const pos = JSON.parse(sessionStorage.getItem('__curpos') || '[640,360]');
        c.style.left = pos[0] + 'px'; c.style.top = pos[1] + 'px';
        document.addEventListener('mousemove', (e) => { c.style.left = e.clientX + 'px'; c.style.top = e.clientY + 'px'; sessionStorage.setItem('__curpos', JSON.stringify([e.clientX, e.clientY])); }, true);
        document.addEventListener('mousedown', (e) => { const r = document.createElement('div'); r.className = '__ripple'; r.style.left = e.clientX + 'px'; r.style.top = e.clientY + 'px'; document.documentElement.appendChild(r); setTimeout(() => r.remove(), 700); }, true);
    };
    if (document.documentElement) mount(); else document.addEventListener('DOMContentLoaded', mount);
    document.addEventListener('DOMContentLoaded', mount);
};

// Thẻ tiêu đề (banner) toàn màn hình. Chạy cả bằng evaluate (hiện ngay) lẫn addInitScript (giữ nguyên qua các lần chuyển trang):
// init script chạy trước khi trang mới vẽ hình đầu tiên nên giao diện web không bị lộ ra ("nháy") trước banner.
const CARD_INIT = (html) => {
    window.__cardHTML = html;   // kịch bản thêm sau ghi đè kịch bản trước; null = ẩn thẻ
    const draw = () => {
        document.getElementById('__card')?.remove();
        if (!window.__cardHTML || !document.documentElement) return;
        const c = document.createElement('div'); c.id = '__card';
        c.setAttribute('style', 'position:fixed;inset:0;z-index:2147483644;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;'
            + 'background:linear-gradient(135deg,#1e3a8a,#2563eb 55%,#0ea5e9);color:#fff;font-family:"Segoe UI",Roboto,Arial,sans-serif;text-align:center;padding:40px;margin:0');
        c.innerHTML = '<style>#__card h1{font-size:46px;font-weight:700;margin:0;color:#fff;line-height:1.2}#__card p{font-size:22px;opacity:.92;margin:0;max-width:900px}'
            + '#__card .tag{font-size:16px;letter-spacing:.12em;text-transform:uppercase;opacity:.8}</style>' + window.__cardHTML;
        document.documentElement.appendChild(c);
    };
    if (document.documentElement) { draw(); return; }
    if (window.__cardObs) return;
    window.__cardObs = true;
    new MutationObserver((_, o) => { if (document.documentElement) { o.disconnect(); draw(); } }).observe(document, { childList: true });
};

// ---------------------------------------------------------------- ghi hình: tự nhận khung hình qua CDP kèm mốc thời gian thật
// (recordVideo của Playwright nhân đôi khung hình khi trang chuyển động nhanh → video dài hơn thời gian thực, tiếng chạy trước hình)
class Capture {
    constructor(page, dir) { this.page = page; this.dir = dir; this.frames = []; this.pending = []; this.last = -1; this.cand = null; }

    async start() {
        fs.rmSync(this.dir, { recursive: true, force: true });
        fs.mkdirSync(this.dir, { recursive: true });
        this.cdp = await this.page.context().newCDPSession(this.page);
        this.cdp.on('Page.screencastFrame', (f) => {
            this.cdp.send('Page.screencastFrameAck', { sessionId: f.sessionId }).catch(() => {});
            const at = Math.max(0, (f.metadata?.timestamp ? f.metadata.timestamp * 1000 : Date.now()) - this.t0);
            const buf = Buffer.from(f.data, 'base64');
            if (at - this.last < 36) {           // nhanh hơn 25 hình/giây: giữ lại hình mới nhất, ghi sau nếu không có hình nào nữa
                this.cand = { buf, at }; clearTimeout(this.timer); this.timer = setTimeout(() => this.flush(), 45); return;
            }
            this.keep(buf, at);
        });
        this.t0 = Date.now();
        await this.cdp.send('Page.startScreencast', { format: 'jpeg', quality: 90, maxWidth: W, maxHeight: H, everyNthFrame: 1 });
    }

    keep(buf, at) {
        clearTimeout(this.timer); this.cand = null;
        this.last = Math.max(at, this.last + 1);
        const file = path.join(this.dir, 'f' + String(this.frames.length).padStart(6, '0') + '.jpg');
        this.frames.push({ at: this.last, file });
        this.pending.push(fs.promises.writeFile(file, buf));
    }

    flush() { if (this.cand) { const c = this.cand; this.keep(c.buf, c.at); } }

    now() { return (Date.now() - this.t0) / 1000; }

    async stop() {
        await this.cdp.send('Page.stopScreencast').catch(() => {});
        this.flush();
        await Promise.all(this.pending);
        await this.cdp.detach().catch(() => {});
    }

    /** Danh sách ffconcat: mỗi khung hình giữ đến đúng thời điểm khung kế tiếp (đơn vị giây thực).
     *  Khoảng tĩnh dài được chia thành các mục ≤ 1 giây lặp cùng một ảnh, và danh sách kéo dài quá `total` 1 giây
     *  (ffmpeg cắt bằng -t) — tránh trường hợp hình cuối không được giữ đủ lâu ở cuối video. */
    concat(total) {
        const fr = this.frames;
        if (!fr.length) throw new Error('Không nhận được khung hình nào từ trình duyệt');
        fr[0].at = 0;
        const lines = ['ffconcat version 1.0'];
        fr.forEach((f, i) => {
            const end = i + 1 < fr.length ? fr[i + 1].at : total * 1000 + 1000;
            let left = Math.max(1, end - f.at);
            while (left > 0) { const d = Math.min(left, 1000); lines.push(`file '${path.basename(f.file)}'`, `duration ${(d / 1000).toFixed(3)}`); left -= d; }
        });
        lines.push(`file '${path.basename(fr[fr.length - 1].file)}'`, 'duration 0.040');
        const list = path.join(this.dir, 'frames.txt');
        fs.writeFileSync(list, lines.join('\n') + '\n');
        return list;
    }
}

// ---------------------------------------------------------------- công cụ cho kịch bản
class Tour {
    constructor(page) { this.page = page; this.mouse = [W / 2, H / 2]; this.caption = ''; }

    async overlay() {
        await this.page.evaluate(OVERLAY).catch(() => {});
        await this.page.evaluate(([t, p]) => {
            const cap = document.getElementById('__cap'); if (cap) { cap.textContent = t; cap.style.display = t ? 'block' : 'none'; }
            const c = document.getElementById('__cur'); if (c) { c.style.left = p[0] + 'px'; c.style.top = p[1] + 'px'; }
        }, [this.caption, this.mouse]).catch(() => {});
    }

    async setCaption(text) { this.caption = text || ''; await this.overlay(); }

    async goto(p) { await this.page.goto(p.startsWith('http') ? p : BASE + p, { waitUntil: 'networkidle' }).catch(() => {}); await this.settle(); }

    async settle(ms = 400) { await this.page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {}); await this.overlay(); await sleep(ms); }

    loc(target) {
        if (typeof target !== 'string') return target;
        if (target.startsWith('text=') || target.startsWith('role=') || /^[.#\[a-z]/i.test(target) && !/\s{2,}/.test(target) && /[.#\[\]=:>]/.test(target)) return this.page.locator(target).first();
        return this.page.getByText(target, { exact: false }).first();
    }

    async moveTo(target, { steps = 28 } = {}) {
        const el = this.loc(target);
        await el.scrollIntoViewIfNeeded({ timeout: 5000 }).catch(() => {});
        let box = await el.boundingBox();
        if (!box) return null;
        // Phần tử sát mép dưới (bị phụ đề che) hoặc sát mép trên → cuộn ra giữa màn hình.
        if (box.y + box.height > H - 150 || box.y < 70) {
            await el.evaluate((n) => n.scrollIntoView({ block: 'center', behavior: 'smooth' })).catch(() => {});
            await sleep(650);
            box = await el.boundingBox();
            if (!box) return null;
        }
        const x = box.x + Math.min(box.width / 2, 60 + box.width * 0.2), y = box.y + box.height / 2;
        await this.page.mouse.move(x, y, { steps });
        this.mouse = [x, y];
        return el;
    }

    /** Di chuột tới, viền sáng phần tử trong ms mili giây. */
    async point(target, ms = 1200) {
        const el = await this.moveTo(target);
        if (!el) return;
        await el.evaluate((n) => n.classList.add('__hl')).catch(() => {});
        await sleep(ms);
        await el.evaluate((n) => n.classList.remove('__hl')).catch(() => {});
    }

    async click(target, { wait = 600 } = {}) {
        const el = await this.moveTo(target);
        if (!el) { console.warn('   ! không thấy:', target); return; }
        await sleep(250);
        await el.click({ timeout: 5000 }).catch((e) => console.warn('   ! bấm lỗi:', target, e.message.split('\n')[0]));
        await this.settle(wait);
    }

    /** Bấm mục menu trái theo đường dẫn (tự mở nhóm menu xổ xuống nếu cần). */
    async nav(pathEnd, { wait = 700 } = {}) {
        const sel = `aside a[href$="${pathEnd}"], .navbar-vertical a[href$="${pathEnd}"]`;
        let link = this.page.locator(sel).first();
        if (!(await link.count())) { await this.goto(pathEnd); return; }
        if (!(await link.isVisible())) {
            const toggle = link.locator('xpath=ancestor::li[contains(@class,"dropdown")][1]/a[contains(@class,"dropdown-toggle")]');
            if (await toggle.count()) await this.click(toggle.first(), { wait: 400 });
        }
        if (await link.isVisible()) await this.click(link, { wait }); else await this.goto(pathEnd);
    }

    async type(target, text, { delay = 55 } = {}) {
        const el = await this.moveTo(target);
        if (!el) return;
        await el.click().catch(() => {});
        if (['date', 'datetime-local', 'time', 'month'].includes(await el.getAttribute('type'))) {
            await el.fill(text).catch(() => {});   // ô ngày: điền giá trị chuẩn (yyyy-mm-dd)
        } else {
            await el.fill('').catch(() => {});
            await el.pressSequentially(text, { delay });
        }
        await sleep(200);
    }

    async scroll(dy, { steps = 14, ms = 900 } = {}) {
        for (let i = 0; i < steps; i++) { await this.page.mouse.wheel(0, dy / steps); await sleep(ms / steps); }
        await sleep(250);
    }

    async scrollTop() { await this.page.evaluate(() => window.scrollTo({ top: 0, behavior: 'smooth' })); await sleep(600); }

    /** Thẻ tiêu đề toàn màn hình; giữ nguyên qua các lần chuyển trang cho tới khi hideCard(). Gọi TRƯỚC goto() để không nháy giao diện. */
    async card(tag, title, sub) {
        const html = `<div class="tag">${tag}</div><h1>${title}</h1>${sub ? `<p>${sub}</p>` : ''}`;
        await this.page.addInitScript(CARD_INIT, html);
        await this.page.evaluate(CARD_INIT, html).catch(() => {});
    }

    async hideCard() {
        await this.page.addInitScript(CARD_INIT, null);
        await this.page.evaluate(CARD_INIT, null).catch(() => {});
    }

    async login(path, email, password, { submit = 'button[type=submit], form button:has-text("Đăng nhập")' } = {}) {
        await this.goto(path);
        await this.type('input[name=email]:visible', email, { delay: 35 });
        await this.type('input[name=password]:visible', password, { delay: 45 });
        await this.click(this.page.locator('form:has(input[name=password]:visible) button:has-text("Đăng nhập")').first(), { wait: 900 });
    }
}

// ---------------------------------------------------------------- quay một kịch bản
async function record(browser, scenario) {
    console.log(`\n▶ ${scenario.slug} — ${scenario.title}`);
    // 1. Giọng đọc cho mọi cảnh (trước khi quay, để biết độ dài từng cảnh)
    const scenes = [];
    for (const [i, s] of scenario.scenes.entries()) {
        const audio = s.say ? await tts(s.say) : null;
        scenes.push({ ...s, audio });
        process.stdout.write(`  🔊 ${i + 1}/${scenario.scenes.length} (${audio ? audio.duration.toFixed(1) + 's' : '—'})\r`);
    }
    console.log(`  🔊 ${scenes.length} lời thuyết minh, tổng ${scenes.reduce((a, s) => a + (s.audio?.duration || 0), 0).toFixed(0)}s`);

    // 2. Quay: mỗi cảnh chạy thao tác trong lúc đọc, chờ đủ thời lượng lời đọc
    const context = await browser.newContext({ viewport: { width: W, height: H }, locale: 'vi-VN', deviceScaleFactor: 1 });
    await context.addInitScript(OVERLAY);
    const page = await context.newPage();
    const tour = new Tour(page);
    page.on('dialog', (d) => d.accept().catch(() => {}));
    // Nền xanh đậm trước khi ghi để khung hình đầu tiên không trắng (banner mở đầu phủ lên ngay sau đó)
    await page.setContent('<!doctype html><html style="background:#1e3a8a"><body></body></html>');
    const cap = new Capture(page, path.join(CACHE, 'video', scenario.slug));
    await cap.start();
    const timeline = [];
    for (const [i, s] of scenes.entries()) {
        const start = cap.now();
        await tour.setCaption(s.caption === false ? '' : (s.caption || s.say || ''));
        timeline.push({ at: start, audio: s.audio });
        try { if (s.do) await s.do(tour, page); } catch (e) { console.warn(`  ! cảnh ${i + 1} lỗi: ${e.message.split('\n')[0]}`); }
        const need = (s.audio ? s.audio.duration : 0) + (s.pause ?? 600) / 1000 - (cap.now() - start);
        if (need > 0) await sleep(need * 1000);
        console.log(`  🎬 cảnh ${i + 1}/${scenes.length} xong (${cap.now().toFixed(0)}s)`);
    }
    await tour.setCaption('');
    await sleep(800);
    const total = cap.now();
    await cap.stop();
    await context.close();
    fs.mkdirSync(path.join(CACHE, 'timeline'), { recursive: true });
    fs.writeFileSync(path.join(CACHE, 'timeline', scenario.slug + '.json'), JSON.stringify({ total, frames: cap.frames.length, scenes: timeline.map((x, i) => ({ i: i + 1, at: +x.at.toFixed(3), audio: x.audio?.duration ?? 0, say: scenes[i].say?.slice(0, 60) })) }, null, 1));

    // 3. Ghép tiếng vào hình (mốc thời gian của tiếng và hình cùng một đồng hồ → không lệch)
    fs.mkdirSync(OUT, { recursive: true });
    const out = path.join(OUT, scenario.slug + '.mp4');
    const withAudio = timeline.filter((x) => x.audio);
    const args = ['-y', '-f', 'concat', '-safe', '0', '-i', cap.concat(total)];
    withAudio.forEach((x) => args.push('-i', x.audio.file));
    const filters = [`[0:v]scale=${W}:${H}:flags=lanczos,format=yuv420p,tpad=stop_mode=clone:stop_duration=2[v]`,
        ...withAudio.map((x, k) => `[${k + 1}:a]adelay=${Math.round(x.at * 1000)}|${Math.round(x.at * 1000)},apad=whole_dur=${total.toFixed(2)}[a${k}]`)];
    const mix = withAudio.map((_, k) => `[a${k}]`).join('') + `amix=inputs=${withAudio.length}:normalize=0:dropout_transition=0,volume=1.6[aout]`;
    args.push('-filter_complex', [...filters, mix].join(';'), '-map', '[v]', '-map', '[aout]',
        '-c:v', 'libx264', '-preset', 'medium', '-crf', '20', '-r', '25', '-fps_mode', 'cfr',
        '-c:a', 'aac', '-b:a', '160k', '-t', total.toFixed(2), '-movflags', '+faststart', out);
    execFileSync('ffmpeg', args, { stdio: ['ignore', 'ignore', 'pipe'] });
    if (!process.env.KEEP_FRAMES) fs.rmSync(cap.dir, { recursive: true, force: true });   // xoá khung hình tạm (vài trăm MB mỗi video)
    console.log(`  ✅ ${path.relative(ROOT, out)} (${(total / 60).toFixed(1)} phút, ${cap.frames.length} khung hình)`);
    return out;
}

// ---------------------------------------------------------------- chạy
const DEMO = JSON.parse(execFileSync('php', ['artisan', 'tutorial:demo', 'info'], { cwd: ROOT }).toString().trim().split('\n').pop());
if (!DEMO.courses?.en) throw new Error('Chưa có dữ liệu demo — chạy: php artisan tutorial:demo setup');
const all = ['quan-tri-vien', 'qtv-noi-dung-website', 'qtv-giao-dien-website', 'qtv-hoc-vien-chung-chi', 'qtv-ky-thi', 'qtv-khoa-hoc-bai-hoc', 'qtv-lop-hoc-tai-khoan', 'qtv-nguoi-dung-phan-quyen', 'nhan-vien', 'giao-vien', 'hoc-vien', 'thi-sinh'];
const wanted = process.argv.slice(2).length ? process.argv.slice(2) : all;
const browser = await chromium.launch();
const done = [];
try {
    for (const slug of wanted) {
        const { default: make } = await import(`./scenarios/${slug}.mjs`);
        const scenario = typeof make === 'function' ? make(DEMO) : make;
        done.push(await record(browser, scenario));
    }
} finally {
    await browser.close();
}
console.log('\nXong:\n' + done.map((f) => '  ' + f).join('\n'));
