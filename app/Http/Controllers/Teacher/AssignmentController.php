<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseSet;
use App\Models\TextbookUnit;
use App\Models\UnitTest;
use App\Support\Exercise\UnitTestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Giao bài cho lớp, bảng điểm, câu nhiều người sai, chấm lại bài Nói / Viết. */
class AssignmentController extends Controller
{
    private function ownClass(Classroom $c): Classroom
    {
        abort_unless(auth()->user()->teaches($c), 404);

        return $c;
    }

    private function own(Assignment $a): Assignment
    {
        $this->ownClass($a->classroom);

        return $a;
    }

    /** Unit của khoá học của lớp + các kỹ năng đã xuất bản. */
    private function units(Classroom $c)
    {
        $tests = app(UnitTestService::class);

        return TextbookUnit::where('textbook_id', $c->course?->textbook_id)->where('skipped', false)->where('status', 'ready')->orderBy('position')->get()
            ->map(function ($u) use ($tests) {
                $u->published_sections = $tests->sections($u);

                return $u;
            });
    }

    public function create(Classroom $classroom): View
    {
        $this->ownClass($classroom)->load('course');

        return view('teacher.assignments.create', ['classroom' => $classroom, 'units' => $this->units($classroom)]);
    }

    public function store(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->ownClass($classroom)->load('course');
        $data = $request->validate([
            'unit_id' => ['required', 'integer'],
            'skills' => ['required', 'array', 'min:1'],
            'skills.*' => [Rule::in(UnitTestService::ORDER)],
            'title' => ['nullable', 'string', 'max:200'],
            'mode' => ['required', Rule::in(array_keys(Assignment::MODES))],
            'opens_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after:opens_at'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:50'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'max_listens' => ['nullable', 'integer', 'min:1', 'max:10'],
            'show_answers' => ['required', Rule::in(['after_submit', 'after_due'])],
        ], ['skills.required' => 'Hãy chọn ít nhất một kỹ năng.', 'due_at.after' => 'Hạn nộp phải sau giờ mở bài.']);

        $unit = $this->units($classroom)->firstWhere('id', (int) $data['unit_id']);
        abort_unless($unit, 422, 'Unit không thuộc khoá học của lớp.');
        // Chốt bộ đề đã xuất bản tại thời điểm giao
        $sections = array_values(array_filter($unit->published_sections, fn ($s) => in_array($s['skill'], $data['skills'], true)));
        if (! $sections) {
            return back()->withInput()->withErrors('Các kỹ năng đã chọn chưa có bộ bài tập được xuất bản cho Unit này.');
        }

        $classroom->assignments()->create([
            'unit_id' => $unit->id,
            'title' => ($data['title'] ?? null) ?: $unit->title.' — '.collect($sections)->map(fn ($s) => ExerciseSet::SKILLS[$s['skill']])->implode(', '),
            'mode' => $data['mode'],
            'sections' => array_map(fn ($s) => ['skill' => $s['skill'], 'set_id' => $s['set_id']], $sections),
            'opens_at' => $data['opens_at'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'max_attempts' => $data['max_attempts'] ?? ($data['mode'] === 'test' ? 1 : null),
            'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
            'max_listens' => $data['max_listens'] ?? null,
            'show_answers' => $data['show_answers'],
        ]);

        return redirect()->route('teacher.classrooms.show', $classroom)->with('status', 'Đã giao bài cho lớp '.$classroom->name.'.');
    }

    /** Đề thi thử: ghép ngẫu nhiên các bộ đề đã xuất bản của nhiều Unit (không tốn AI), mặc định chế độ Kiểm tra có tính giờ. */
    public function mock(Classroom $classroom): View
    {
        $this->ownClass($classroom)->load('course');
        $units = $this->units($classroom);
        $pool = ExerciseSet::whereIn('unit_id', $units->pluck('id'))->where('status', 'published')->whereHas('questions')
            ->selectRaw('unit_id, skill, COUNT(*) n')->groupBy('unit_id', 'skill')->get()->groupBy('unit_id');

        return view('teacher.assignments.mock', ['classroom' => $classroom, 'units' => $units, 'pool' => $pool]);
    }

    public function storeMock(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->ownClass($classroom)->load('course');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['integer'],
            'counts' => ['required', 'array'],
            'counts.*' => ['nullable', 'integer', 'min:0', 'max:5'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'opens_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after:opens_at'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:20'],
            'max_listens' => ['nullable', 'integer', 'min:1', 'max:10'],
            'show_answers' => ['required', Rule::in(['after_submit', 'after_due'])],
        ], ['unit_ids.required' => 'Chọn ít nhất một Unit.', 'due_at.after' => 'Hạn nộp phải sau giờ mở bài.']);

        $unitIds = $this->units($classroom)->pluck('id')->intersect(array_map('intval', $data['unit_ids']))->values();
        abort_if($unitIds->isEmpty(), 422, 'Unit không thuộc khoá học của lớp.');

        $sections = [];
        $missing = [];
        foreach (UnitTestService::ORDER as $skill) {
            $want = (int) ($data['counts'][$skill] ?? 0);
            if ($want === 0) {
                continue;
            }
            // Mỗi Unit lấy bộ xuất bản mới nhất của kỹ năng → bốc ngẫu nhiên $want bộ từ các Unit đã chọn
            $sets = ExerciseSet::whereIn('unit_id', $unitIds)->where('skill', $skill)->where('status', 'published')->whereHas('questions')
                ->latest('published_at')->latest('id')->get()->unique('unit_id')->shuffle()->take($want);
            if ($sets->count() < $want) {
                $missing[] = ExerciseSet::SKILLS[$skill].' (có '.$sets->count().'/'.$want.')';
            }
            foreach ($sets as $set) {
                $sections[] = ['skill' => $skill, 'set_id' => $set->id];
            }
        }
        if (! $sections) {
            return back()->withInput()->withErrors('Các Unit đã chọn chưa có bộ bài tập xuất bản cho kỹ năng nào đã yêu cầu.');
        }

        $first = ExerciseSet::find($sections[0]['set_id']);
        $classroom->assignments()->create([
            'unit_id' => $first->unit_id,
            'kind' => 'mock',
            'title' => $data['title'],
            'mode' => 'test',
            'sections' => $sections,
            'opens_at' => $data['opens_at'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'max_attempts' => $data['max_attempts'] ?? 1,
            'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
            'max_listens' => $data['max_listens'] ?? null,
            'show_answers' => $data['show_answers'],
        ]);

        return redirect()->route('teacher.classrooms.show', $classroom)->with('status', 'Đã giao đề thi thử gồm '.count($sections).' phần.'.($missing ? ' Không đủ bộ đề: '.implode(', ', $missing).' — đã lấy hết số có.' : ''));
    }

    /** Bảng điểm: mỗi học viên 1 dòng (lần làm điểm cao nhất), câu nhiều người sai, bài cần chấm lại. */
    public function show(Assignment $assignment): View
    {
        $this->own($assignment)->load('classroom.students', 'unit');
        $tests = $assignment->tests()->whereIn('status', ['completed', 'grading'])->get()->groupBy('student_id');

        $rows = $assignment->classroom->students->sortBy('last_name')->map(function ($st) use ($tests) {
            $mine = $tests->get($st->id, collect());
            $best = $mine->where('status', 'completed')->sortByDesc('total_score')->first() ?? $mine->sortByDesc('id')->first();

            return ['student' => $st, 'test' => $best, 'count' => $mine->count()];
        });

        // Câu nhiều người sai (Đọc / Nghe / Viết lại câu) — trên lần làm tốt nhất của mỗi học viên
        $attemptIds = $rows->pluck('test')->filter()->flatMap(fn ($t) => array_column($t->sections, 'attempt_id'))->filter();
        $wrong = [];
        foreach (ExerciseAttempt::whereIn('id', $attemptIds)->where('status', 'graded')->get() as $a) {
            foreach ($a->result ?? [] as $r) {
                if (in_array($r['type'], ['speaking', 'read_aloud', 'paragraph'], true)) {
                    continue;
                }
                $k = $a->exercise_set_id.'|'.$r['code'];
                $wrong[$k] ??= ['code' => $r['code'], 'prompt' => $r['prompt'], 'answer' => $r['answer'], 'wrong' => 0, 'total' => 0];
                $wrong[$k]['total']++;
                $wrong[$k]['wrong'] += $r['correct'] ? 0 : 1;
            }
        }
        $wrong = collect($wrong)->filter(fn ($w) => $w['wrong'] > 0)->sortByDesc(fn ($w) => $w['wrong'] / max(1, $w['total']))->take(10)->values();

        // Bài Nói / Viết cần chấm lại (máy gắn cờ hoặc chưa được giáo viên xem)
        $review = ExerciseAttempt::whereIn('unit_test_id', $assignment->tests()->pluck('id'))
            ->whereHas('set', fn ($q) => $q->whereIn('skill', ['speaking', 'writing']))
            ->where('status', 'graded')->whereNull('reviewed_at')->with('student', 'set')->latest('id')->get();

        return view('teacher.assignments.show', compact('assignment', 'rows', 'wrong', 'review'));
    }

    public function export(Assignment $assignment): StreamedResponse
    {
        $this->own($assignment)->load('classroom.students');
        $skills = array_column($assignment->sections, 'skill');
        $tests = $assignment->tests()->where('status', 'completed')->get()->groupBy('student_id');
        $name = 'bang-diem-'.\Str::slug($assignment->classroom->name.'-'.$assignment->title).'.csv';

        return response()->streamDownload(function () use ($assignment, $skills, $tests) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM để Excel đọc đúng tiếng Việt
            fputcsv($out, array_merge(['Họ tên', 'Email', 'Số lần làm', 'Điểm tổng', 'Trình độ'], array_map(fn ($s) => ExerciseSet::SKILLS[$s], $skills), ['Nộp lúc']));
            foreach ($assignment->classroom->students->sortBy('last_name') as $st) {
                $mine = $tests->get($st->id, collect());
                $best = $mine->sortByDesc('total_score')->first();
                fputcsv($out, array_merge(
                    [$st->full_name, $st->email, $mine->count(), $best?->total_score ?? '', $best?->level ?? 'Chưa làm'],
                    array_map(fn ($s) => $best?->skill_scores[$s] ?? '', $skills),
                    [$best?->finished_at?->format('d/m/Y H:i') ?? '']
                ));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Xem bài làm của 1 học viên + chấm lại từng phần. */
    public function review(Assignment $assignment, UnitTest $test): View
    {
        $this->own($assignment);
        abort_unless($test->assignment_id === $assignment->id, 404);
        $attempts = ExerciseAttempt::whereIn('id', array_filter(array_column($test->sections, 'attempt_id')))->with('set')->get()->keyBy('id');

        return view('teacher.assignments.review', ['assignment' => $assignment, 'test' => $test->load('student', 'unit'), 'attempts' => $attempts]);
    }

    public function saveReview(Request $request, Assignment $assignment, ExerciseAttempt $attempt, UnitTestService $tests): RedirectResponse
    {
        $this->own($assignment);
        abort_unless($attempt->unitTest?->assignment_id === $assignment->id, 404);
        $data = $request->validate([
            'teacher_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'teacher_comment' => ['nullable', 'string', 'max:2000'],
        ]);
        $attempt->update([
            'teacher_score' => $data['teacher_score'] ?? null,
            'teacher_comment' => $data['teacher_comment'] ?? null,
            'reviewed_at' => now(),
        ]);
        $tests->refresh($attempt->unitTest); // tính lại điểm tổng

        return back()->with('status', 'Đã lưu điểm chấm lại.');
    }

    /** Nghe ghi âm bài Nói của học viên. */
    public function recording(Assignment $assignment, ExerciseAttempt $attempt, string $code): BinaryFileResponse
    {
        $this->own($assignment);
        abort_unless($attempt->unitTest?->assignment_id === $assignment->id, 404);
        $path = $attempt->files[$code]['path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        $classroom = $this->own($assignment)->classroom;
        $assignment->delete();

        return redirect()->route('teacher.classrooms.show', $classroom)->with('status', 'Đã xoá bài giao (bài làm của học viên vẫn được giữ trong lịch sử).');
    }
}
