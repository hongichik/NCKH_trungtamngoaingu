@extends('layouts.portal')

@section('title', $lesson->title)

@php
    $me = auth('student')->user();
    $mark = trim($me->full_name.' · '.($me->email ?: $me->phone));
@endphp

@push('styles')
<style>
    .lp-stage { position: relative; background: #000; border-radius: .6rem; overflow: hidden; aspect-ratio: 16 / 9; user-select: none; }
    .lp-stage video, .lp-stage .lp-iframe { width: 100%; height: 100%; display: block; border: 0; }
    .lp-wm { position: absolute; z-index: 5; pointer-events: none; color: rgba(255,255,255,.38); font-size: clamp(.7rem, 1.4vw, 1rem); font-weight: 600; text-shadow: 0 0 3px rgba(0,0,0,.6); white-space: nowrap; transition: left 1.2s, top 1.2s; }
    .lp-overlay { position: absolute; inset: 0; z-index: 6; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 1rem; color: #fff; background: rgba(0,0,0,.7); }
    .lp-start { position: absolute; inset: 0; z-index: 7; border: 0; background: rgba(0,0,0,.35); color: #fff; font-size: 1.2rem; }
    .lp-stage:fullscreen { aspect-ratio: auto; border-radius: 0; }
</style>
@endpush

@section('content')
    <nav class="small mb-2"><a href="{{ route('portal.classes.index') }}">Lớp học của tôi</a> / <a href="{{ route('portal.classes.show', $classroom) }}">{{ $classroom->name }}</a> / Bài học</nav>
    <h1 class="h4 mb-1">{{ $lesson->title }}</h1>
    @if ($lesson->summary)<p class="text-muted">{{ $lesson->summary }}</p>@endif
    @php $exCount = $lesson->exercisesFor($classroom)->count(); @endphp
    @if ($exCount)
        {{-- Điện thoại: bài tập nằm cuối trang → nút nhảy nhanh xuống bài tập --}}
        <a href="#lesson-exercises" class="btn btn-primary btn-sm d-lg-none mb-3"><i class="bi bi-pencil-square"></i> Đến bài tập ({{ $exCount }})</a>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            @foreach ($lesson->videos as $v)
                @php $p = $progress[$v->id] ?? null; @endphp
                <div class="card border-0 shadow-sm mb-3 lp-box" data-source="{{ route('portal.video.source', $v) }}" data-progress="{{ route('portal.video.progress', $v) }}">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-play-btn"></i><span class="fw-semibold flex-fill">{{ $v->title }}</span>
                            @if ($v->duration_seconds)<span class="small text-muted">{{ gmdate($v->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $v->duration_seconds) }}</span>@endif
                            <span class="badge text-bg-success lp-done {{ $p?->completed_at ? '' : 'd-none' }}">Đã xem</span>
                        </div>
                        <div class="lp-stage">
                            @if ($v->provider !== 'bunny')
                                <video playsinline controls controlsList="nodownload noremoteplayback" disablePictureInPicture preload="metadata"></video>
                            @endif
                            <div class="lp-wm">{{ $mark }}</div>
                            <div class="lp-overlay d-none"></div>
                            <button type="button" class="lp-start"><i class="bi bi-play-circle fs-1 d-block"></i>Bấm để xem</button>
                        </div>
                        @if ($v->provider !== 'bunny')
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <select class="form-select form-select-sm w-auto lp-rate" aria-label="Tốc độ">
                                    @foreach ([0.75, 1, 1.25, 1.5, 2] as $r)<option value="{{ $r }}" @selected($r == 1)>{{ $r }}x</option>@endforeach
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-secondary lp-fs"><i class="bi bi-fullscreen"></i> Toàn màn hình</button>
                                <div class="progress flex-fill" style="height: 1.1rem" title="Đã xem"><div class="progress-bar lp-progress" style="width: {{ $p?->percent ?? 0 }}%">{{ $p?->percent ?? 0 }}%</div></div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            @if ($lesson->content)
                {{-- Ghi chú: dòng "TỪ VỰNG:", "NGỮ PHÁP:"… in đậm thành tiêu đề; dòng hội thoại "Tên: lời" in đậm tên người nói. --}}
                <div class="card border-0 shadow-sm mb-3"><div class="card-body"><h2 class="h6">Ghi chú bài học</h2>
                    <div class="lesson-notes">
                        @foreach (preg_split('/\r?\n/', trim($lesson->content)) as $line)
                            @if (preg_match('/^([\p{Lu}][\p{Lu}\s&\/]{2,}):\s*(.*)$/u', $line, $m))
                                <div class="mt-2"><span class="fw-semibold text-primary">{{ $m[1] }}</span>@if ($m[2] !== '')<span>: {{ $m[2] }}</span>@endif</div>
                            @elseif (preg_match('/^([^\s:：]{1,24}(?:\s[^\s:：]{1,24})?)\s*[:：]\s*(.+)$/u', $line, $m) && mb_strlen($m[1]) <= 24)
                                <div class="ps-2"><span class="fw-semibold">{{ $m[1] }}:</span> {{ $m[2] }}</div>
                            @elseif (trim($line) === '')
                                <div class="mb-2"></div>
                            @else
                                <div>{{ $line }}</div>
                            @endif
                        @endforeach
                    </div>
                </div></div>
            @endif

            @if (! empty($lesson->vocabulary))
                @php $lzh = $classroom->course?->language === 'zh'; @endphp
                <div class="card border-0 shadow-sm mb-3"><div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h2 class="h6 mb-0 me-auto">Từ vựng của bài ({{ count($lesson->vocabulary) }})</h2>
                        <a href="{{ route('portal.vocab.study', ['deck' => 'l'.$lesson->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-card-heading"></i> Học thẻ từ của bài</a>
                        @if ($lzh)
                            <a href="{{ route('portal.vocab.drill', 'l'.$lesson->id) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-ui-radios"></i> Luyện thanh điệu</a>
                            <a href="{{ route('portal.vocab.writing', 'l'.$lesson->id) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-brush"></i> Tập viết chữ Hán</a>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($lesson->vocabulary as $w)
                            <span class="badge text-bg-light border fw-normal text-wrap text-start" style="font-size: .85rem">
                                <b>{{ $w['word'] }}</b>@if (! empty($w['pinyin'])) <span class="text-muted">{{ $w['pinyin'] }}</span>@endif
                                @if (! empty($w['meaning_vi'])) — {{ $w['meaning_vi'] }}@endif
                            </span>
                        @endforeach
                    </div>
                </div></div>
            @endif

            @if (! empty($profile['vocabulary']) || ! empty($profile['grammar_points']))
                <div class="card border-0 shadow-sm mb-3"><div class="card-body">
                    @if (! empty($profile['vocabulary']))
                        <h2 class="h6">Từ vựng</h2>
                        <div class="d-flex flex-wrap gap-2 mb-3">@foreach (array_slice($profile['vocabulary'], 0, 60) as $w)<span class="badge text-bg-light border">{{ $w['word'] ?? $w['term'] ?? '' }}{{ ! empty($w['meaning_vi']) ? ' — '.$w['meaning_vi'] : '' }}</span>@endforeach</div>
                    @endif
                    @if (! empty($profile['grammar_points']))
                        <h2 class="h6">Ngữ pháp</h2>
                        <ul class="mb-0">@foreach ($profile['grammar_points'] as $g)<li>{{ is_array($g) ? ($g['name'] ?? $g['title'] ?? json_encode($g, JSON_UNESCAPED_UNICODE)) : $g }}</li>@endforeach</ul>
                    @endif
                </div></div>
            @endif
        </div>

        <div class="col-lg-4">
            @if ($lesson->materials->isNotEmpty())
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold">Tài liệu</div>
                    <div class="list-group list-group-flush">
                        @foreach ($lesson->materials as $m)
                            <a href="{{ route('portal.lessons.material', $m) }}" target="_blank" class="list-group-item list-group-item-action"><i class="bi bi-file-earmark"></i> {{ $m->title }}</a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div id="lesson-exercises" style="scroll-margin-top: 70px">
                @includeIf('lessons.portal.exercises', ['classroom' => $classroom, 'lesson' => $lesson, 'videosDone' => $videosDone])
            </div>

            <div class="d-flex gap-2">
                @if ($prev)<a href="{{ route('portal.lessons.show', [$classroom, $prev]) }}" class="btn btn-outline-secondary btn-sm">‹ {{ \Illuminate\Support\Str::limit($prev->title, 25) }}</a>@endif
                @if ($next)<a href="{{ route('portal.lessons.show', [$classroom, $next]) }}" class="btn btn-outline-secondary btn-sm ms-auto">{{ \Illuminate\Support\Str::limit($next->title, 25) }} ›</a>@endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @if ($lesson->videos->contains('provider', 'hls'))<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js"></script>@endif
    @if ($lesson->videos->contains('provider', 'bunny'))<script src="https://assets.mediadelivery.net/playerjs/player-0.1.0.min.js"></script>@endif
    <script src="{{ asset('js/lesson-player.js') }}?v=1"></script>
@endpush
