@extends($layout)

@section('title', 'Bài làm của '.$attempt->student->full_name)

@section('content')
    <div class="mb-3"><a href="{{ route($prefix.'.custom.results', [$classroom, $cce]) }}"><i class="ti ti-arrow-left me-1"></i>{{ $cce->exercise->title }}</a></div>
    <form method="POST" action="{{ route($prefix.'.custom.review.save', [$classroom, $cce, $attempt]) }}">@csrf @method('PUT')
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-3 align-items-end">
                <div><div class="text-muted small">Điểm chấm</div><div class="h2 mb-0">{{ $attempt->score !== null ? number_format((float) $attempt->score, 1) : '—' }}<span class="fs-5 text-muted">/10</span></div>
                    <div class="small text-muted">{{ rtrim(rtrim(number_format((float) $attempt->earned, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format((float) $attempt->max, 2), '0'), '.') }} · nộp {{ $attempt->submitted_at?->format('H:i d/m/Y') }}
                        @if ($attempt->status === 'grading') · <span class="text-warning">còn phần chờ chấm</span>@endif</div></div>
                <div><label class="form-label">Giáo viên sửa điểm (thang 10)</label><input type="number" step="0.1" min="0" max="10" name="teacher_score" class="form-control" value="{{ $attempt->teacher_score }}" style="width: 8rem"></div>
                <div class="flex-fill"><label class="form-label">Nhận xét chung</label><input name="teacher_comment" class="form-control" value="{{ $attempt->teacher_comment }}"></div>
                <button class="btn btn-primary">Lưu</button>
            </div>
        </div>

        @foreach ($cce->exercise->items as $item)
            @php $r = ($attempt->result ?? [])[$item->id] ?? null; $t = $item->type(); $answer = ($attempt->answers ?? [])[$item->id] ?? []; @endphp
            @if ($r && $t)
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title">Phần {{ $loop->iteration }}{{ $item->title ? ': '.$item->title : '' }} <span class="small text-muted">· {{ $t->name }}</span></h3>
                        <div class="card-actions">{{ $r['pending'] ? 'Chờ chấm' : ($r['score']['scaled'] ?? '') }} · {{ rtrim(rtrim(number_format($r['score']['earned'], 2), '0'), '.') }}/{{ rtrim(rtrim(number_format($r['score']['max'], 2), '0'), '.') }}</div>
                    </div>
                    @if ($t->engine === 'writing')
                        <div class="card-body border-bottom"><div class="small text-muted mb-1">{{ $item->content['prompt'] ?? '' }}</div><div class="p-2 bg-light rounded" style="white-space: pre-wrap">{{ $answer['text'] ?? '(bỏ trống)' }}</div></div>
                    @elseif ($t->engine === 'speaking')
                        <div class="card-body border-bottom">
                            @if (! empty(($attempt->files ?? [])[$item->id]))<audio controls src="{{ route($prefix.'.custom.recording', [$classroom, $cce, $attempt, $item->id]) }}"></audio>@else<span class="text-muted">Không có ghi âm.</span>@endif
                        </div>
                    @else
                        <div class="table-responsive border-bottom">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Ý</th><th>Trả lời</th><th>Đáp án</th><th class="text-end">Điểm</th></tr></thead>
                                <tbody>@foreach ($r['parts'] as $p)<tr class="{{ $p['correct'] ? 'table-success' : 'table-danger' }}"><td>{{ $p['label'] }}</td><td>{{ $p['given'] ?: '—' }}{{ $p['reason'] ? ' ('.$p['reason'].')' : '' }}</td><td>{{ $p['expected'] }}</td><td class="text-end">{{ $p['earned'] }}/{{ $p['max'] }}</td></tr>@endforeach</tbody>
                            </table>
                        </div>
                    @endif
                    @if (! empty($r['feedback']))
                        <div class="card-body border-bottom small">
                            <div class="text-muted">Chấm bởi {{ ($r['feedback']['by'] ?? '') === 'teacher' ? 'giáo viên' : 'AI' }}</div>
                            @foreach ($r['feedback']['criteria'] ?? [] as $c)<div><b>{{ $c['name'] }}: {{ $c['score'] }}</b> — {{ $c['comment'] }}</div>@endforeach
                            @if (! empty($r['feedback']['comment']))<div>{{ $r['feedback']['comment'] }}</div>@endif
                            @if (! empty($r['feedback']['transcript']))<div class="text-muted">Chép lời: “{{ $r['feedback']['transcript'] }}”</div>@endif
                        </div>
                    @endif
                    @if (in_array($t->engine, ['writing', 'speaking'], true))
                        <div class="card-footer d-flex flex-wrap gap-2 align-items-end">
                            <div><label class="form-label small">Điểm phần này (0–10)</label><input type="number" step="0.5" min="0" max="10" name="items[{{ $item->id }}][score]" class="form-control form-control-sm" style="width: 7rem" placeholder="{{ $r['pending'] ? 'chấm' : number_format($r['score']['ten'] ?? 0, 1) }}"></div>
                            <div class="flex-fill"><label class="form-label small">Nhận xét phần này</label><input name="items[{{ $item->id }}][comment]" class="form-control form-control-sm"></div>
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
        @foreach (collect($attempt->result ?? [])->except($cce->exercise->items->pluck('id')->all()) as $r)
            <div class="card mb-3 opacity-75"><div class="card-header"><h3 class="card-title">{{ $r['title'] ?? 'Phần đã bỏ khỏi bài' }} <span class="badge bg-secondary-lt">đã bỏ khỏi bài</span></h3>
                <div class="card-actions">{{ rtrim(rtrim(number_format($r['score']['earned'] ?? 0, 2), '0'), '.') }}/{{ rtrim(rtrim(number_format($r['score']['max'] ?? 0, 2), '0'), '.') }}</div></div></div>
        @endforeach
        <button class="btn btn-primary">Lưu điểm</button>
    </form>
@endsection
