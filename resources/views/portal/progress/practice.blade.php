@extends('layouts.portal')

@section('title', 'Bài luyện riêng #'.$practice->id)

@if ($practice->status === 'generating')
    @push('styles')<meta http-equiv="refresh" content="6">@endpush
@endif

@section('content')
    <nav class="small mb-2"><a href="{{ route('portal.mistakes.index') }}">Sổ lỗi sai</a> / Bài luyện riêng #{{ $practice->id }}</nav>
    <h1 class="h4 mb-3">Bài luyện riêng #{{ $practice->id }}</h1>

    @if ($practice->status === 'generating')
        <div class="alert alert-info d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span> AI đang soạn bài luyện từ {{ count($practice->source) }} câu bạn làm sai… Trang tự tải lại.</div>
    @elseif ($practice->status === 'failed')
        <div class="alert alert-danger">Chưa soạn được bài luyện: {{ $practice->error }}. Hãy thử tạo lại từ <a href="{{ route('portal.mistakes.index') }}">Sổ lỗi sai</a>.</div>
    @else
        @php $done = $practice->status === 'done'; @endphp
        @if ($done)
            <div class="alert alert-success">Điểm: <strong>{{ rtrim(rtrim(number_format($practice->score, 1), '0'), '.') }}/10</strong> — đúng {{ collect($practice->result)->where('correct', true)->count() }}/{{ count($practice->questions) }} câu.</div>
        @endif
        <form method="POST" action="{{ route('portal.practice.submit', $practice) }}">
            @csrf
            @foreach ($practice->questions as $i => $q)
                @php $r = $practice->result[$q['code']] ?? null; @endphp
                <div class="card shadow-sm border-0 mb-2 {{ $done ? ($r['correct'] ?? false ? 'border-start border-success border-3' : 'border-start border-danger border-3') : '' }}">
                    <div class="card-body">
                        <div class="small text-muted mb-1">Câu {{ $i + 1 }} · {{ $q['focus_vi'] ?? '' }}</div>
                        @include('portal.progress.partials.question', ['q' => $q, 'name' => 'answers['.$q['code'].']', 'given' => $r['given'] ?? '', 'disabled' => $done])
                        @if ($done)
                            <div class="small mt-2">@unless ($r['correct'] ?? false)<span class="text-danger">Đáp án: <strong>{{ $q['answer'] === 'True' ? 'Đúng' : ($q['answer'] === 'False' ? 'Sai' : $q['answer']) }}</strong></span> · @endunless<span class="text-muted">{{ $q['explanation_vi'] }}</span></div>
                        @endif
                    </div>
                </div>
            @endforeach
            @unless ($done)<button class="btn btn-success"><i class="bi bi-send"></i> Nộp bài</button>@endunless
        </form>
        @if ($done)<a href="{{ route('portal.mistakes.index') }}" class="btn btn-outline-secondary mt-2">Về sổ lỗi sai</a>@endif
    @endif
@endsection
