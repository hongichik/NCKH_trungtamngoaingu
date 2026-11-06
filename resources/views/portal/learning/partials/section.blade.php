{{--
    Thân 1 bộ bài tập (bài đọc / trình phát + câu hỏi). Dùng chung cho trang luyện 1 bộ và trang làm bài 4 kỹ năng.
    Biến: $set, $passage, $cloze, $questions, $audio, $recorded, $course, $prefix (tên trường, vd "answers" hoặc "answers[12]")
--}}
        <div class="row g-4">
            @if ($set->skill === 'reading')
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 position-sticky" style="top: 1rem">
                        <div class="card-header bg-white fw-semibold"><i class="bi bi-book"></i> Bài đọc</div>
                        <div class="card-body" style="white-space: pre-wrap; max-height: 75vh; overflow: auto">{{ $passage }}</div>
                    </div>
                </div>
            @else
                <div class="col-12">
                    <div class="card shadow-sm border-0 player" data-audio='@json($audio)'>
                        <div class="card-body d-flex flex-wrap align-items-center gap-2">
                            <button type="button" class="btn btn-primary player-play"><i class="bi bi-play-fill"></i> Nghe</button>
                            <button type="button" class="btn btn-outline-secondary player-stop"><i class="bi bi-stop-fill"></i></button>
                            <select class="form-select form-select-sm w-auto player-rate" aria-label="Tốc độ">
                                <option value="0.75">0.75x</option>
                                <option value="1" selected>1x</option>
                            </select>
                            <span class="small text-muted ms-auto player-note">
                                @if (($audio['mode'] ?? '') === 'textbook') Audio của giáo trình
                                @elseif (($audio['mode'] ?? '') === 'tts') Giọng đọc do AI tạo
                                @else Giọng đọc do máy tạo (trình duyệt) @endif
                            </span>
                            @if (($audio['mode'] ?? '') === 'textbook')
                                <div class="w-100 mt-2">
                                    @foreach ($audio['srcs'] as $k => $src)
                                        <div class="small text-muted">Phần {{ $k + 1 }}/{{ count($audio['srcs']) }}</div>
                                        <audio class="w-100 audio-part" preload="metadata" src="{{ $src }}" controls></audio>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="{{ $set->skill === 'reading' ? 'col-lg-6' : 'col-12' }}">
                @if ($cloze)
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-header bg-white fw-semibold">Đoạn văn điền từ</div>
                        <div class="card-body" style="white-space: pre-wrap">{{ $cloze }}</div>
                    </div>
                @endif

                @foreach ($questions as $q)
                    <div class="card shadow-sm border-0 mb-3" data-question>
                        <div class="card-body">
                            <div class="fw-semibold mb-2"><span class="badge text-bg-secondary me-1">{{ $q['code'] }}</span>{{ $q['type'] === 'read_aloud' ? 'Đọc to câu sau:' : '' }}</div>
                            <div class="mb-2 {{ $q['type'] === 'read_aloud' ? 'fs-5 p-2 bg-light rounded' : '' }}" style="white-space: pre-wrap">{{ $q['prompt'] }}</div>
                            @if (in_array($q['type'], ['mcq', 'cloze', 'true_false'], true))
                                @foreach ($q['options'] as $j => $opt)
                                    @php $val = $q['type'] === 'true_false' ? $opt : \App\Support\Exercise\Answers::letter($opt); @endphp
                                    <label class="form-check d-block py-1">
                                        <input class="form-check-input" type="radio" name="{{ $prefix }}[{{ $q['code'] }}]" value="{{ $val }}">
                                        <span class="form-check-label">{{ $q['type'] === 'true_false' ? ['True' => 'Đúng (True)', 'False' => 'Sai (False)'][$opt] ?? $opt : $opt }}</span>
                                    </label>
                                @endforeach
                            @elseif ($q['type'] === 'ordering')
                                <div class="ordering">
                                <div class="small text-muted mb-1">Bấm các câu theo đúng thứ tự:</div>
                                <div class="d-flex flex-column gap-1 mb-2 ordering-options">
                                    @foreach ($q['options'] as $opt)
                                        <button type="button" class="btn btn-outline-secondary btn-sm text-start" data-letter="{{ strtolower(\App\Support\Exercise\Answers::letter($opt)) }}">{{ $opt }}</button>
                                    @endforeach
                                </div>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" name="{{ $prefix }}[{{ $q['code'] }}]" placeholder="Thứ tự bạn chọn sẽ hiện ở đây" readonly>
                                    <button type="button" class="btn btn-outline-danger ordering-reset">Làm lại</button>
                                </div>
                                </div>
                            @elseif ($q['type'] === 'transformation')
                                <div class="small text-muted mb-1">Viết lại câu trên sao cho nghĩa không đổi, bắt đầu bằng:</div>
                                <input type="text" class="form-control" name="{{ $prefix }}[{{ $q['code'] }}]" autocomplete="off" value="{{ $q['meta']['start'] ?? '' }}" data-start="{{ $q['meta']['start'] ?? '' }}">
                            @elseif ($q['type'] === 'paragraph')
                                @include('portal.learning.partials.hints', ['meta' => $q['meta']])
                                <textarea class="form-control word-count" rows="9" name="{{ $prefix }}[{{ $q['code'] }}]" data-lang="{{ $course->language }}" data-min="{{ $q['meta']['min_words'] ?? 0 }}" data-max="{{ $q['meta']['max_words'] ?? 0 }}" placeholder="Viết đoạn văn của bạn ở đây…"></textarea>
                                <div class="small text-muted mt-1"><span class="wc">0</span> / {{ $q['meta']['min_words'] ?? '' }}–{{ $q['meta']['max_words'] ?? '' }} {{ $course->language === 'zh' ? 'chữ' : 'từ' }}</div>
                            @elseif (in_array($q['type'], ['speaking', 'read_aloud'], true))
                                @if ($q['type'] === 'speaking')
                                    @include('portal.learning.partials.hints', ['meta' => $q['meta']])
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-secondary mb-2 sample-say" data-text="{{ $q['prompt'] }}"><i class="bi bi-volume-up"></i> Nghe mẫu</button>
                                @endif
                                <div class="recorder d-flex flex-wrap align-items-center gap-2" data-url="{{ route('portal.learning.record', [$course, $set, $q['code']]) }}" data-recorded="{{ in_array($q['code'], $recorded, true) ? 1 : 0 }}" data-max="{{ min(120, ($q['meta']['time_seconds'] ?? 60) + 30) }}">
                                    <button type="button" class="btn btn-danger rec-start"><i class="bi bi-mic-fill"></i> Ghi âm</button>
                                    <button type="button" class="btn btn-outline-danger rec-stop d-none"><i class="bi bi-stop-fill"></i> Dừng (<span class="rec-time">0</span>s)</button>
                                    <audio class="rec-play {{ in_array($q['code'], $recorded, true) ? '' : 'd-none' }}" controls></audio>
                                    <span class="small rec-status {{ in_array($q['code'], $recorded, true) ? 'text-success' : 'text-muted' }}">{{ in_array($q['code'], $recorded, true) ? '✓ Đã lưu ghi âm' : 'Gợi ý thời gian: '.($q['meta']['time_seconds'] ?? 30).' giây' }}</span>
                                </div>
                            @else
                                <input type="text" class="form-control" name="{{ $prefix }}[{{ $q['code'] }}]" autocomplete="off" placeholder="Điền từ nghe được">
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
