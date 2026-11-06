{{-- Hộp xác nhận xoá bài học (tránh xoá nhầm). Nút mở: data-delete-lesson="{url}" data-title data-exercises data-videos data-materials data-attempts --}}
<div class="modal modal-blur fade" id="lesson-delete-modal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form method="POST" class="modal-content" id="lesson-delete-form">@csrf @method('DELETE')
            <div class="modal-status bg-danger"></div>
            <div class="modal-body text-center py-4">
                <i class="ti ti-alert-triangle text-danger" style="font-size: 3rem"></i>
                <h3 class="mt-2">Xoá bài học?</h3>
                <div class="fw-semibold mb-2" data-f="title"></div>
                <div class="text-muted small text-start">Xoá <b>vĩnh viễn</b>, không khôi phục được:
                    <ul class="mb-0 ps-3">
                        <li><span data-f="exercises"></span> bài tập (kể cả bài tập riêng của lớp)</li>
                        <li><span data-f="videos"></span> video, <span data-f="materials"></span> tài liệu</li>
                        <li data-f="attempts-row" class="text-danger fw-semibold"><span data-f="attempts"></span> lượt làm bài của học viên và kết quả</li>
                    </ul>
                </div>
                <label class="form-check text-start mt-3 mb-0">
                    <input type="checkbox" class="form-check-input" name="confirm" value="1" id="lesson-delete-confirm">
                    <span class="form-check-label">Tôi hiểu và muốn xoá bài học này</span>
                </label>
            </div>
            <div class="modal-footer">
                <div class="w-100"><div class="row">
                    <div class="col"><button type="button" class="btn w-100" data-bs-dismiss="modal">Huỷ</button></div>
                    <div class="col"><button class="btn btn-danger w-100" id="lesson-delete-submit" disabled>Xoá bài học</button></div>
                </div></div>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
(() => {
    const modalEl = document.getElementById('lesson-delete-modal');
    const form = document.getElementById('lesson-delete-form');
    const box = document.getElementById('lesson-delete-confirm');
    const submit = document.getElementById('lesson-delete-submit');
    const f = (k) => form.querySelector(`[data-f="${k}"]`);
    box.addEventListener('change', () => { submit.disabled = !box.checked; });
    document.querySelectorAll('[data-delete-lesson]').forEach((b) => b.addEventListener('click', () => {
        form.action = b.dataset.deleteLesson;
        f('title').textContent = '“' + b.dataset.title + '”';
        ['exercises', 'videos', 'materials', 'attempts'].forEach((k) => { f(k).textContent = b.dataset[k] || '0'; });
        f('attempts-row').hidden = !(+b.dataset.attempts);
        box.checked = false; submit.disabled = true;
        const BS = window.bootstrap || window.tabler;   // Tabler để Bootstrap ở window.tabler
        BS.Modal.getOrCreateInstance(modalEl).show();
    }));
})();
</script>
@endpush
