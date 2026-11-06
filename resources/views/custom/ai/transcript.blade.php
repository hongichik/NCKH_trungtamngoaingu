{{-- Lời máy chép từ file nghe (speech-to-text) — AI dùng để soạn / dựng đề khớp với bài nghe. Biến: $a (file nghe), $course, $import, $ai --}}
@if (empty($a['tts']))
    @php $st = $a['transcript_status'] ?? null; @endphp
    <div class="mt-1" data-transcript="{{ $a['id'] }}" data-transcript-status="{{ $st }}">
        @if ($st === 'pending')
            <div class="small text-primary"><span class="spinner-border spinner-border-sm me-1"></span>Đang chép lời file nghe…</div>
        @elseif ($st === 'done')
            <details>
                <summary class="small text-success"><i class="ti ti-file-text me-1"></i>Lời đã chép từ file nghe (AI dùng để soạn đề)</summary>
                <div class="small bg-light rounded p-2 mt-1" style="white-space: pre-wrap; max-height: 220px; overflow: auto">{{ $a['transcript'] }}</div>
            </details>
        @else
            <div class="small {{ $st ? 'text-danger' : 'text-muted' }}">
                {{ $st ? 'Chưa chép được lời: '.($a['transcript_error'] ?? 'lỗi') : 'Chưa chép lời file nghe.' }}
            </div>
        @endif
        @if ($st !== 'pending')
            <form method="POST" action="{{ route($ai.'.audio.transcribe', [$course, $import, $a['id']]) }}" class="mt-1">@csrf
                <button class="btn btn-sm btn-ghost-primary px-1 py-0"><i class="ti ti-refresh me-1"></i>{{ $st === 'done' ? 'Chép lời lại' : 'Chép lời file nghe' }}</button>
            </form>
        @endif
    </div>
@endif
