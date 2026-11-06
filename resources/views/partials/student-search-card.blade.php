{{-- Tìm học viên đã có (tài khoản / hồ sơ thí sinh) và thêm vào lớp. Biến: $searchUrl, $addUrl --}}
<form method="POST" action="{{ $addUrl }}" class="card mb-3" id="student-search">@csrf
    <div class="card-header"><h3 class="card-title">Thêm học viên đã có tài khoản</h3></div>
    <div class="card-body">
        <input type="search" class="form-control" id="ss-q" placeholder="Gõ họ tên, email, số điện thoại hoặc CCCD (ít nhất 2 ký tự)" autocomplete="off">
        <div class="list-group list-group-flush mt-2" id="ss-results" style="max-height: 320px; overflow: auto"></div>
        <div class="form-hint mt-1">Hồ sơ thí sinh có email nhưng chưa có mật khẩu sẽ được cấp mật khẩu khi thêm (hiện một lần). Hồ sơ chưa có email không thêm được.</div>
    </div>
    <div class="card-footer d-flex align-items-center"><span class="small text-muted" id="ss-count">Chưa chọn ai</span><button class="btn btn-primary ms-auto" id="ss-add" disabled>Thêm vào lớp</button></div>
</form>
@push('scripts')
<script>
(function () {
    const q = document.getElementById('ss-q'), box = document.getElementById('ss-results'), btn = document.getElementById('ss-add'), count = document.getElementById('ss-count');
    const picked = new Map();
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const sync = () => {
        btn.disabled = picked.size === 0;
        count.textContent = picked.size ? 'Đã chọn ' + picked.size + ': ' + [...picked.values()].join(', ') : 'Chưa chọn ai';
        document.querySelectorAll('#student-search input[name="student_ids[]"]').forEach((i) => i.remove());
        picked.forEach((n, id) => { const h = document.createElement('input'); h.type = 'hidden'; h.name = 'student_ids[]'; h.value = id; document.getElementById('student-search').appendChild(h); });
    };
    let timer;
    q.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            if (q.value.trim().length < 2) { box.innerHTML = ''; return; }
            const r = await fetch(@json($searchUrl) + '?q=' + encodeURIComponent(q.value.trim()), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const j = await r.json();
            box.innerHTML = j.results.length ? '' : '<div class="list-group-item text-muted small">Không tìm thấy.</div>';
            j.results.forEach((s) => {
                const disabled = s.in_class || !s.can_add;
                const el = document.createElement('label');
                el.className = 'list-group-item d-flex gap-2 align-items-center' + (disabled ? ' text-muted' : '');
                el.innerHTML = '<input type="checkbox" class="form-check-input m-0"' + (disabled ? ' disabled' : '') + (picked.has(String(s.id)) ? ' checked' : '') + '>'
                    + '<span class="flex-fill"><b>' + esc(s.name) + '</b> <span class="small">' + esc(s.email || 'chưa có email') + (s.phone ? ' · ' + esc(s.phone) : '') + (s.dob ? ' · ' + esc(s.dob) : '') + '</span></span>'
                    + (s.in_class ? '<span class="badge bg-secondary-lt">đã ở trong lớp</span>' : (!s.can_add ? '<span class="badge bg-red-lt">chưa có email</span>' : (!s.has_login ? '<span class="badge bg-yellow-lt">hồ sơ thí sinh — sẽ cấp mật khẩu</span>' : '<span class="badge bg-green-lt">có tài khoản</span>')));
                el.querySelector('input').addEventListener('change', (e) => { e.target.checked ? picked.set(String(s.id), s.name) : picked.delete(String(s.id)); sync(); });
                box.appendChild(el);
            });
        }, 250);
    });
})();
</script>
@endpush
