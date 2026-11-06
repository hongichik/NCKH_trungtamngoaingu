@extends('layouts.portal')

@php $titles = ['tu-vung' => 'Từ vựng', 'ngu-phap' => 'Ngữ pháp', 'bai-doc' => 'Bài đọc']; @endphp

@section('title', $titles[$kind].' · '.$unit->title)

@section('content')
    @php $cls = $classroom; @endphp
    <nav class="small mb-2"><a href="{{ route('portal.classes.index') }}">Lớp học của tôi</a>@if ($cls) / <a href="{{ route('portal.classes.show', $cls) }}">{{ $cls->name }}</a>@endif / {{ $titles[$kind] }} · {{ $unit->title }}</nav>
    <div class="d-flex align-items-center mb-3">
        <h1 class="h4 mb-0">{{ $titles[$kind] }} — {{ $unit->title }}</h1>
        @if ($zh && $kind !== 'tu-vung')<button type="button" class="btn btn-outline-primary btn-sm ms-auto" id="py-toggle"><i class="bi bi-translate"></i> <span>Hiện pinyin</span></button>@endif
        <a href="{{ $classroom ? route('portal.classes.show', $classroom) : route('portal.classes.index') }}" class="btn btn-outline-secondary btn-sm {{ $zh && $kind !== 'tu-vung' ? 'ms-2' : 'ms-auto' }}"><i class="bi bi-arrow-left"></i> Quay lại</a>
    </div>

    @if ($kind === 'tu-vung')
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light"><tr><th>Từ</th>@if ($zh)<th>Pinyin</th>@endif<th>Loại</th><th>Nghĩa</th><th class="d-none d-md-table-cell">Ví dụ</th><th></th></tr></thead>
                    @foreach ($profile['vocabulary'] ?? [] as $v)
                        <tr>
                            <td class="fw-semibold">{{ $v['word'] }}</td>
                            @if ($zh)<td>{{ $v['pinyin'] ?? '' }}</td>@endif
                            <td class="text-muted small">{{ $v['pos'] ?? '' }}</td>
                            <td>{{ $v['meaning_vi'] ?? '' }}</td>
                            <td class="small text-muted d-none d-md-table-cell zh-text">{{ $v['example'] ?? '' }}</td>
                            <td><button type="button" class="btn btn-sm btn-link p-0 speak" data-text="{{ $v['word'] }}" title="Nghe"><i class="bi bi-volume-up fs-5"></i></button></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    @elseif ($kind === 'ngu-phap')
        <div class="card shadow-sm border-0">
            <ul class="list-group list-group-flush">
                @foreach ($profile['grammar_points'] ?? [] as $g)
                    <li class="list-group-item">
                        <div class="fw-semibold">{{ $g['name'] }}</div>
                        <div class="small">{{ $g['explanation_vi'] ?? '' }}</div>
                        @if (! empty($g['example']))<div class="small text-muted fst-italic zh-text">{{ $g['example'] }}</div>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        @foreach ($profile['passages'] ?? [] as $p)
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-book"></i> {{ $p['title'] ?: 'Bài đọc' }}</div>
                <div class="card-body zh-text" style="white-space: pre-wrap">{{ $p['text'] }}</div>
            </div>
        @endforeach
        @foreach ($profile['dialogues'] ?? [] as $d)
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-chat-dots"></i> {{ $d['title'] ?: 'Hội thoại' }}</div>
                <div class="card-body">@foreach ($d['lines'] ?? [] as $l)<div class="mb-1"><strong>{{ $l['speaker'] }}:</strong> <span class="zh-text">{{ $l['text'] }}</span></div>@endforeach</div>
            </div>
        @endforeach
    @endif
@endsection

@push('styles')
<style>
    .zh-text ruby rt { font-size: .6em; color: #0a4a75; }
    .zh-text.py-off ruby rt { display: none; }
    .zh-text ruby { ruby-position: over; }
</style>
@endpush

@push('scripts')
@if ($zh)
<script src="https://cdn.jsdelivr.net/npm/pinyin-pro@3.26.0/dist/index.js"></script>
<script>
// Pinyin trên chữ Hán (<ruby>), mặc định ẩn — bấm nút để hiện
(function () {
    const els = document.querySelectorAll('.zh-text');
    let on = false, built = false;
    const btn = document.getElementById('py-toggle');
    function build() {
        if (built || !window.pinyinPro) return;
        built = true;
        els.forEach((el) => { el.innerHTML = pinyinPro.html(el.innerHTML, { wrapNonChinese: false }); // đầu vào đã escape sẵn → không chèn được HTML el.classList.add('py-off'); });
    }
    btn && btn.addEventListener('click', () => {
        build();
        on = !on;
        els.forEach((el) => el.classList.toggle('py-off', !on));
        btn.querySelector('span').textContent = on ? 'Ẩn pinyin' : 'Hiện pinyin';
    });
})();
</script>
@endif
<script src="{{ asset('js/speech.js') }}?v=2"></script>
<script>
document.querySelectorAll('.speak').forEach((b) => b.addEventListener('click', () => AppSpeech.speakNow(b.dataset.text, @json($zh ? 'zh-CN' : 'en-US'), 0.85)));
</script>
@endpush
