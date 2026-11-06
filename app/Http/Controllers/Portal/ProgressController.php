<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AiJob;
use App\Models\PracticeSet;
use App\Models\Student;
use App\Models\UnitTest;
use App\Support\Exercise\Scorer;
use App\Support\Progress\Gamification;
use App\Support\Progress\MistakeBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Tiến độ (XP, chuỗi ngày, huy hiệu, bản đồ năng lực), báo cáo in PDF, sổ lỗi sai, bài luyện riêng do AI soạn. */
class ProgressController extends Controller
{
    public function __construct(private Gamification $game, private MistakeBook $mistakes) {}

    private function student(): Student
    {
        return Auth::guard('student')->user();
    }

    /** Bản đồ năng lực: mỗi Unit — điểm tổng cao nhất + điểm cao nhất từng kỹ năng. */
    private function competency(Student $student)
    {
        return UnitTest::where('student_id', $student->id)->where('status', 'completed')
            ->where(fn ($q) => $q->whereNull('assignment_id')->orWhereHas('assignment', fn ($a) => $a->where('kind', 'unit'))) // đề thi thử gồm nhiều Unit → không tính vào ô Unit
            ->with('unit:id,title,textbook_id,position', 'unit.textbook:id,title')
            ->get()->groupBy('unit_id')->map(function ($tests) {
                $skills = [];
                foreach ($tests as $t) {
                    foreach ($t->skill_scores ?? [] as $sk => $sc) {
                        $skills[$sk] = max($skills[$sk] ?? 0, (float) $sc);
                    }
                }

                return ['unit' => $tests->first()->unit, 'best' => $tests->max('total_score'), 'count' => $tests->count(), 'skills' => $skills, 'last' => $tests->max('finished_at')];
            })->filter(fn ($r) => $r['unit'])->sortBy(fn ($r) => [$r['unit']->textbook_id, $r['unit']->position])->values()
            // + bài tập trong bài học (khoá học kiểu mới)
            ->concat(app(\App\Support\Progress\LessonCompetency::class)->forStudent($student))->values();
    }

    private function data(Student $student): array
    {
        $stat = $this->game->stats($student);
        $classes = $student->classrooms()->where('show_leaderboard', true)->get();

        return [
            'student' => $student,
            'stat' => $stat,
            'streak' => $this->game->currentStreak($stat),
            'today' => $this->game->todayXp($student),
            'daily' => $this->game->daily($student),
            'badges' => $student->badges()->get()->groupBy('code'),
            'competency' => $this->competency($student),
            'boards' => $classes->map(fn ($c) => ['classroom' => $c, 'rows' => $this->game->leaderboard($c)]),
        ];
    }

    public function index(): View
    {
        return view('portal.progress.index', $this->data($this->student()));
    }

    public function report(): View
    {
        return view('portal.progress.report', $this->data($this->student()) + ['mistakeCount' => $this->mistakes->items($this->student())->count()]);
    }

    public function goal(Request $request): RedirectResponse
    {
        $data = $request->validate(['daily_goal' => ['required', Rule::in(array_keys(Gamification::GOALS))]]);
        $this->game->stats($this->student())->update(['daily_goal' => (int) $data['daily_goal']]);

        return back()->with('status', 'Đã đặt mục tiêu mỗi ngày: '.$data['daily_goal'].' XP.');
    }

    public function mistakes(): View
    {
        $student = $this->student();
        $items = $this->mistakes->items($student);

        return view('portal.progress.mistakes', [
            'items' => $items->groupBy('unit'),
            'count' => $items->count(),
            'context' => $this->mistakes->context($items),
            'results' => session('retry_results', []),
            'practices' => PracticeSet::where('student_id', $student->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function retry(Request $request): RedirectResponse
    {
        $answers = (array) $request->validate(['answers' => ['required', 'array', 'max:100'], 'answers.*' => ['nullable', 'string', 'max:500']])['answers'];
        $r = $this->mistakes->retry($this->student(), array_filter($answers, fn ($v) => $v !== null && $v !== ''));
        $xp = $this->game->award($this->student(), 'mistake', $r['correct'] * 2);

        return redirect()->route('portal.mistakes.index')->with('retry_results', $r['results'])
            ->with('status', "Làm lại đúng {$r['correct']}/{$r['total']} câu".($xp['points'] ? " · +{$xp['points']} XP" : '').'. Câu đúng được chuyển sang "đã sửa".');
    }

    /** AI soạn bài luyện riêng từ các câu sai gần nhất. */
    public function createPractice(): RedirectResponse
    {
        $student = $this->student();
        if (PracticeSet::where('student_id', $student->id)->where('status', 'generating')->exists()) {
            return back()->with('status_error', 'AI đang soạn một bài luyện khác, đợi xong nhé.');
        }
        if (PracticeSet::where('student_id', $student->id)->where('created_at', '>=', today())->count() >= 5) {
            return back()->with('status_error', 'Mỗi ngày tạo tối đa 5 bài luyện riêng.');
        }
        $items = $this->mistakes->items($student);
        if ($items->isEmpty()) {
            return back()->with('status_error', 'Chưa có câu sai nào để luyện.');
        }
        $lang = $items->countBy('language')->sortDesc()->keys()->first();
        $source = $items->where('language', $lang)->take(8)->map(fn ($i) => array_intersect_key($i, array_flip(['key', 'type', 'prompt', 'options', 'given', 'answer', 'explanation'])))->values()->all();
        $set = PracticeSet::create(['student_id' => $student->id, 'language' => $lang, 'source' => $source, 'status' => 'generating']);
        AiJob::dispatch('practice_generate', ['practice_id' => $set->id], 'practice:'.$set->id, 0, 'student:'.$student->id);

        return redirect()->route('portal.practice.show', $set);
    }

    private function own(PracticeSet $practice): PracticeSet
    {
        abort_unless($practice->student_id === $this->student()->id, 404);

        return $practice;
    }

    public function showPractice(PracticeSet $practice): View
    {
        return view('portal.progress.practice', ['practice' => $this->own($practice)]);
    }

    public function submitPractice(Request $request, PracticeSet $practice, Scorer $scorer): RedirectResponse
    {
        $this->own($practice);
        abort_unless($practice->status === 'ready', 422);
        $answers = (array) $request->input('answers', []);
        $result = [];
        $correct = 0;
        foreach ($practice->questions as $q) {
            $given = trim((string) ($answers[$q['code']] ?? ''));
            $ok = $scorer->isCorrect($q['type'], mb_substr($given, 0, 300), $q['answer'], $practice->language);
            $correct += $ok ? 1 : 0;
            $result[$q['code']] = ['given' => $given, 'correct' => $ok];
        }
        $total = count($practice->questions);
        $practice->update(['result' => $result, 'score' => $total ? round($correct / $total * 10, 1) : 0, 'status' => 'done']);
        $xp = $this->game->award($this->student(), 'practice', $correct * 3, ['practice_id' => $practice->id]);

        return redirect()->route('portal.practice.show', $practice)->with('status', "Đúng {$correct}/{$total} câu".($xp['points'] ? " · +{$xp['points']} XP" : '').'.');
    }
}
