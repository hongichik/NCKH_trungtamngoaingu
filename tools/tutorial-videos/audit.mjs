// Rà soát giao diện: đăng nhập từng vai trò, chụp ảnh mọi trang (máy tính + điện thoại), ghi lỗi JS / HTTP / tràn ngang.
//   node audit.mjs [thư mục ảnh]      (BASE_URL mặc định http://127.0.0.1:8765 — để không làm chậm máy chủ đang quay video)
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '../..');
const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8765').replace(/\/$/, '');
const OUT = process.argv[2] || path.join(HERE, '.cache/audit');
fs.mkdirSync(OUT, { recursive: true });
const D = JSON.parse(execFileSync('php', ['artisan', 'tutorial:demo', 'info'], { cwd: ROOT }).toString().trim().split('\n').pop());
const en = D.courses.en, zh = D.courses.zh, c = D.classrooms.en.id, l = en.lessons, ex = l[0].exercises[0];
const ATTEMPT = process.env.ATTEMPT || '12';

const roles = {
    public: { pages: ['/', '/tin-tuc', '/dang-ky-chung-chi', '/admin/login', '/giao-vien/dang-nhap'] },
    admin: { login: ['/admin/login', 'demo.admin'], pages: ['/admin', '/admin/posts', '/admin/categories', '/admin/pages', '/admin/galleries', '/admin/banners', '/admin/menus', '/admin/settings',
        '/admin/contacts', '/admin/students', '/admin/certificates', '/admin/exams?type=hsk', '/admin/exam-registrations?type=hsk', '/admin/exam-registrations?type=cntt', '/admin/teachers',
        '/admin/courses', `/admin/courses/${en.id}/edit`, `/admin/courses/${en.id}/lessons`, `/admin/courses/${en.id}/lessons/${l[0].id}`, `/admin/courses/${zh.id}/lessons/${zh.lessons[0].id}`,
        `/admin/courses/${en.id}/custom-exercises/${ex}`, `/admin/courses/${en.id}/custom-exercises/${ex}/xem-truoc`, `/admin/courses/${en.id}/ai-import?lesson=${l[0].id}`,
        '/admin/classrooms', `/admin/classrooms/${c}`, `/admin/classrooms/${c}/bai-tap/${ex}/ket-qua`, '/admin/student-accounts', '/admin/users', '/admin/question-types'] },
    teacher: { login: ['/giao-vien/dang-nhap', 'demo.giaovien'], pages: ['/giao-vien', '/giao-vien/lop-hoc', `/giao-vien/lop-hoc/${c}`, `/giao-vien/lop-hoc/${c}/bai-tap/${ex}/ket-qua`,
        `/giao-vien/lop-hoc/${c}/nang-luc`, '/giao-vien/khoa-hoc', `/giao-vien/khoa-hoc/${en.id}/bai-hoc`, `/giao-vien/khoa-hoc/${en.id}/bai-hoc/${l[1].id}`,
        `/giao-vien/khoa-hoc/${en.id}/bai-tap-tuy-chinh/${ex}`, `/giao-vien/khoa-hoc/${en.id}/dung-bai-ai?lesson=${l[1].id}`] },
    learner: { login: ['/dang-ky-chung-chi', 'demo.hocvien', true], mobile: true, pages: ['/thi-sinh', '/thi-sinh/lop-hoc', `/thi-sinh/lop-hoc/${c}`, `/thi-sinh/lop-hoc/${c}/bai-hoc/${l[1].id}`,
        `/thi-sinh/lop-hoc/${D.classrooms.zh.id}/bai-hoc/${zh.lessons[0].id}`, `/thi-sinh/bai-lam-tuy-chinh/${ATTEMPT}/ket-qua`, '/thi-sinh/on-tu-vung', '/thi-sinh/so-loi-sai', '/thi-sinh/tien-do', '/thi-sinh/ket-qua', '/thi-sinh/thong-tin'] },
    candidate: { login: ['/dang-ky-chung-chi', 'demo.thisinh', true], mobile: true, pages: ['/thi-sinh', '/thi-sinh/thong-tin?type=hsk', '/thi-sinh/dang-ky-thi/hsk', '/thi-sinh/dang-ky-thi/cntt', '/thi-sinh/ket-qua'] },
};

const browser = await chromium.launch();
const report = [];
for (const [role, cfg] of Object.entries(roles)) {
    for (const vp of cfg.mobile ? [['desk', 1280, 800], ['mob', 390, 844]] : [['desk', 1280, 800]]) {
        const ctx = await browser.newContext({ viewport: { width: vp[1], height: vp[2] }, locale: 'vi-VN', isMobile: vp[0] === 'mob', hasTouch: vp[0] === 'mob' });
        const page = await ctx.newPage();
        const errs = [];
        page.on('pageerror', (e) => errs.push('JS: ' + e.message.split('\n')[0]));
        page.on('console', (m) => { if (m.type() === 'error') errs.push('console: ' + m.text().slice(0, 160)); });
        page.on('response', (r) => { if (r.status() >= 400 && !r.url().includes('favicon')) errs.push(r.status() + ' ' + r.url().replace(BASE, '')); });
        if (cfg.login) {
            const [p, user, portal] = cfg.login;
            await page.goto(BASE + p);
            if (portal) await page.click('#login-tab');
            const scope = portal ? '#login-pane ' : '';
            await page.fill(scope + 'input[name=email]', user + D.domain);
            await page.fill(scope + 'input[name=password]', D.password);
            await Promise.all([page.waitForNavigation().catch(() => {}), page.click(scope + 'button:has-text("Đăng nhập")')]);
        }
        for (const [i, p] of cfg.pages.entries()) {
            errs.length = 0;
            const r = await page.goto(BASE + p, { waitUntil: 'networkidle' }).catch((e) => ({ status: () => 'ERR ' + e.message }));
            await page.waitForTimeout(300);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth).catch(() => 0);
            const name = `${role}-${vp[0]}-${String(i).padStart(2, '0')}`;
            await page.screenshot({ path: path.join(OUT, name + '.jpg'), fullPage: true, type: 'jpeg', quality: 70 }).catch(() => {});
            const row = { name, url: p, status: r?.status?.(), final: page.url().replace(BASE, ''), overflowX: overflow > 2 ? overflow : 0, errors: [...new Set(errs)] };
            report.push(row);
            console.log(`${name} ${row.status} ${p}${row.final !== p ? ' → ' + row.final : ''}${row.overflowX ? ' ⟷' + overflow : ''}${row.errors.length ? ' ⚠ ' + row.errors.join(' | ') : ''}`);
        }
        await ctx.close();
    }
}
await browser.close();
fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(report, null, 2));
