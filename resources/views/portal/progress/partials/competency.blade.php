{{-- Bản đồ năng lực: Unit × kỹ năng (điểm cao nhất) --}}
@php
    $skills = \App\Models\ExerciseSet::SKILLS;
    // Bài tập trong bài học có thêm nhóm "Từ vựng & ngữ pháp"
    $hasVocab = isset($competency) ? $competency->contains(fn ($r) => isset($r['skills']['vocab'])) : collect($skillAvg ?? [])->contains(fn ($r) => isset($r['vocab']));
    if ($hasVocab) { $skills += ['vocab' => 'Từ vựng & NP']; }
    $cell = fn ($v) => $v === null ? 'bg-light text-muted' : ($v >= 8 ? 'bg-success text-white' : ($v >= 6.5 ? 'bg-primary text-white' : ($v >= 5 ? 'bg-warning' : 'bg-danger text-white')));
    $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.');
@endphp
@if ($competency->isEmpty())
    <div class="text-muted small">Chưa có bài làm nào được chấm xong.</div>
@else
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle text-center mb-0">
            <thead class="table-light"><tr><th class="text-start">Unit / Bài học</th>@foreach ($skills as $label)<th>{{ $label }}</th>@endforeach<th>Tổng (cao nhất)</th><th>Số lần</th></tr></thead>
            @foreach ($competency as $row)
                <tr>
                    <td class="text-start small">{{ $row['unit']->title }}</td>
                    @foreach ($skills as $sk => $label)
                        @php $v = $row['skills'][$sk] ?? null; @endphp
                        <td class="{{ $cell($v) }}">{{ $fmt($v) }}</td>
                    @endforeach
                    <td class="fw-bold {{ $cell($row['best']) }}">{{ $fmt($row['best']) }}</td>
                    <td class="small text-muted">{{ $row['count'] }}</td>
                </tr>
            @endforeach
        </table>
    </div>
    <div class="small text-muted mt-1"><span class="badge bg-success">≥ 8</span> <span class="badge bg-primary">≥ 6.5</span> <span class="badge bg-warning text-dark">≥ 5</span> <span class="badge bg-danger">&lt; 5</span> — kỹ năng màu đỏ / vàng là chỗ cần luyện thêm.</div>
@endif
