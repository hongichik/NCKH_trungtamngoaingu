{{-- Nút phát lời thoại bằng giọng đọc AI (public/js/speech.js; AI tắt thì dùng giọng trình duyệt) khi chưa có file audio. Lời thoại mã hoá base64 để không hiện sẵn trên trang. --}}
<div class="qt-listen">
    <button type="button" class="qt-btn qt-btn-primary" data-speak="{{ base64_encode($text) }}"><span aria-hidden="true">▶</span> {{ $label ?? 'Nghe' }}</button>
    <select class="qt-rate" aria-label="Tốc độ"><option value="0.75">0.75x</option><option value="0.9" selected>1x</option><option value="1.1">1.25x</option></select>
    <button type="button" class="qt-btn qt-btn-ghost" data-speak-stop>■</button>
</div>
