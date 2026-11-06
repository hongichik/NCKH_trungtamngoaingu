{{--
    Trình soạn nội dung bài tập dạng form (public/js/qt-editor.js) — không cần sửa JSON.
    Biến: $type (QuestionType), $json (chuỗi JSON nội dung hiện tại), $name (tên trường gửi lên, mặc định "content")
--}}
<div class="qte" data-engine="{{ $type->engine }}" data-options='@json($type->options ?: new stdClass)'>
    <div data-qte-form></div>
    <details class="mt-2" data-qte-advanced>
        <summary class="small text-muted">Nâng cao: sửa JSON trực tiếp</summary>
        <textarea name="{{ $name ?? 'content' }}" class="form-control font-monospace small mt-1" rows="12" spellcheck="false" data-qte-json>{{ $json }}</textarea>
        <div class="form-hint">Đang mở mục này thì nội dung lấy theo ô JSON. Đóng lại để quay về form.</div>
    </details>
</div>
@once
    @push('scripts')
        <script src="{{ asset('js/qt-editor.js') }}?v=1"></script>
    @endpush
@endonce
