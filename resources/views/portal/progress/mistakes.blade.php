@extends('layouts.portal')

@section('title', 'Sổ lỗi sai')

@php $skills = \App\Models\ExerciseSet::SKILLS; @endphp

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h1 class="h4 mb-0">Sổ lỗi sai <span class="badge text-bg-danger">{{ $count }}</span></h1>
        @if ($count)
            <form method="POST" action="{{ route('portal.practice.create') }}" class="ms-auto">@csrf
                <button class="btn btn-outline-primary"><i class="bi bi-stars"></i> AI soạn bài luyện tương tự</button>
            </form>
        @endif
    </div>
    <p class="text-muted small">Các câu Đọc / Nghe bạn đã làm sai (chỉ hiện khi bài đã được xem đáp án). Làm lại ngay bên dưới — câu làm đúng được chuyển sang <em>đã sửa</em>. Hoặc để AI soạn bài luyện mới cùng kiến thức.</p>

    @if ($practices->isNotEmpty())
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-stars"></i> Bài luyện riêng của tôi</div>
            <div class="list-group list-group-flush">
                @foreach ($practices as $p)
                    <a href="{{ route('portal.practice.show', $p) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                        <span>Bài luyện #{{ $p->id }} · {{ count($p->source) }} lỗi gốc</span>
                        <span class="small text-muted">{{ $p->created_at->format('H:i d/m') }}</span>
                        <span class="badge ms-auto {{ ['generating' => 'text-bg-info', 'ready' => 'text-bg-warning', 'done' => 'text-bg-success', 'failed' => 'text-bg-danger'][$p->status] }}">{{ \App\Models\PracticeSet::STATUS_LABELS[$p->status] }}@if ($p->status === 'done') · {{ rtrim(rtrim(number_format($p->score, 1), '0'), '.') }}đ @endif</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if (! empty($results))
        @php $ok = collect($results)->where('correct', true)->count(); @endphp
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Kết quả làm lại: đúng {{ $ok }}/{{ count($results) }}</div>
            <ul class="list-group list-group-flush small">
                @foreach ($results as $r)
                    <li class="list-group-item"><i class="bi {{ $r['correct'] ? 'bi-check-circle text-success' : 'bi-x-circle text-danger' }}"></i>
                        Bạn trả lời: <strong>{{ $r['given'] }}</strong> @unless ($r['correct'])· Đáp án: <strong class="text-success">{{ $r['answer'] }}</strong>@endunless
                        @if ($r['explanation'])<div class="text-muted">{{ $r['explanation'] }}</div>@endif</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($count === 0)
        <div class="alert alert-success"><i class="bi bi-emoji-smile"></i> Không còn lỗi sai nào cần sửa. Tuyệt vời!</div>
    @else
        <form method="POST" action="{{ route('portal.mistakes.retry') }}">
            @csrf
            @foreach ($items as $unit => $list)
                <h2 class="h6 mt-3">{{ $unit ?: 'Khác' }} <span class="text-muted fw-normal">({{ $list->count() }})</span></h2>
                @foreach ($list as $it)
                    <div class="card shadow-sm border-0 mb-2">
                        <div class="card-body">
                            <div class="small text-muted mb-1">{{ $skills[$it['skill']] ?? '' }} · sai ngày {{ $it['wrong_at']->format('d/m') }} · lần trước bạn chọn: <span class="text-danger">{{ $it['given'] !== '' ? $it['given'] : '(bỏ trống)' }}</span></div>
                            @if (! empty($context[$it['set_id']]['text']))
                                <details class="small mb-2"><summary class="text-primary">Xem lại {{ $it['skill'] === 'listening' ? 'lời thoại' : 'bài đọc' }}</summary><div class="p-2 bg-light rounded mt-1" style="white-space: pre-wrap; max-height: 260px; overflow: auto">{{ $context[$it['set_id']]['cloze'] && $it['type'] === 'cloze' ? $context[$it['set_id']]['cloze'] : $context[$it['set_id']]['text'] }}</div></details>
                            @endif
                            @include('portal.progress.partials.question', ['q' => $it, 'name' => 'answers['.$it['key'].']'])
                        </div>
                    </div>
                @endforeach
            @endforeach
            <div class="position-sticky bottom-0 bg-white py-2 border-top"><button class="btn btn-success"><i class="bi bi-check2-all"></i> Chấm phần làm lại</button></div>
        </form>
    @endif
@endsection
