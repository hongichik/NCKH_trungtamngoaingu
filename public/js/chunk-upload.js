/*
 * Tải file lớn theo từng mảnh 2 MB (hosting giới hạn 8 MB / request). Dùng với App\Support\ChunkedUpload.
 * ChunkUpload.send(file, url, {params, onProgress}) → Promise<json của mảnh cuối>
 */
window.ChunkUpload = (function () {
    const PART = 2 * 1024 * 1024;
    const csrf = () => (document.querySelector('meta[name=csrf-token]') || {}).content || (document.querySelector('input[name=_token]') || {}).value;

    async function send(file, url, opts = {}) {
        const total = Math.max(1, Math.ceil(file.size / PART));
        const upload = Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
        let last = null;
        for (let i = 0; i < total; i++) {
            const qs = new URLSearchParams(Object.assign({ upload, part: i, total, name: file.name }, opts.params || {}));
            let ok = false, tries = 0, j = null;
            while (!ok && tries < 3) {
                tries++;
                try {
                    const r = await fetch(url + (url.includes('?') ? '&' : '?') + qs, {
                        method: 'POST', body: file.slice(i * PART, (i + 1) * PART), credentials: 'same-origin',
                        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/octet-stream' },
                    });
                    j = await r.json().catch(() => ({}));
                    if (!r.ok || j.ok === false) throw new Error(j.message || ('Lỗi ' + r.status));
                    ok = true;
                } catch (e) {
                    if (tries >= 3) throw e;
                    await new Promise((res) => setTimeout(res, 1000 * tries));
                }
            }
            last = j;
            if (opts.onProgress) opts.onProgress((i + 1) / total);
        }
        return last;
    }

    /** Đọc thời lượng video trên máy (không cần máy chủ có ffmpeg). */
    function duration(file) {
        return new Promise((resolve) => {
            const v = document.createElement('video');
            v.preload = 'metadata';
            v.onloadedmetadata = () => { URL.revokeObjectURL(v.src); resolve(Math.round(v.duration) || 0); };
            v.onerror = () => resolve(0);
            v.src = URL.createObjectURL(file);
        });
    }

    return { send, duration };
})();
