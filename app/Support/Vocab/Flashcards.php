<?php

namespace App\Support\Vocab;

use App\Models\Assignment;
use App\Models\CourseLesson;
use App\Models\Student;
use App\Models\TextbookUnit;
use App\Models\VocabReview;
use Illuminate\Support\Collection;

/**
 * Flashcard từ vựng, lên lịch ôn theo SM-2 rút gọn (4 nút: Quên / Khó / Nhớ / Dễ). Nguồn thẻ:
 *  - Unit giáo trình đã được giao trong lớp của học viên (V1);
 *  - bài học của khoá (khoá học → bài học): danh sách từ vựng của bài (AI lập, giáo viên sửa), bài học đang mở với lớp.
 */
class Flashcards
{
    public const GRADES = ['again' => 'Quên', 'hard' => 'Khó', 'good' => 'Nhớ', 'easy' => 'Dễ'];

    /** Số từ mới tối đa mỗi lượt học. */
    public const NEW_PER_SESSION = 15;

    public static function key(string $word): string
    {
        return sha1(mb_strtolower(trim($word)));
    }

    /** Các bộ thẻ của học viên (Unit được giao + bài học đang mở có từ vựng). @return Collection<int, Deck> */
    public function decks(Student $student): Collection
    {
        return $this->loadDecks($student);
    }

    private function loadDecks(Student $student): Collection
    {
        $classes = $student->classrooms()->get();
        if ($classes->isEmpty()) {
            return collect();
        }
        $unitIds = Assignment::whereIn('classroom_id', $classes->pluck('id'))->where('kind', 'unit')->where(fn ($q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->distinct()->pluck('unit_id');
        $units = TextbookUnit::whereIn('id', $unitIds)->with('profile', 'textbook:id,language,title')->orderBy('textbook_id')->orderBy('position')->get()
            ->map(fn ($u) => Deck::fromUnit($u));

        $lessons = collect();
        foreach ($classes->whereNotNull('course_id') as $class) {
            foreach (CourseLesson::where('course_id', $class->course_id)->where('is_published', true)->whereNotNull('vocabulary')->with('course:id,title,language')->orderBy('position')->orderBy('id')->get() as $l) {
                if (! $lessons->has($l->id) && $l->openFor($class)) {
                    $lessons[$l->id] = Deck::fromLesson($l);
                }
            }
        }

        return $units->concat($lessons->values())->filter(fn (Deck $d) => ! empty($this->words($d)))->values();
    }

    /** Bộ thẻ theo id ("u12", "l34", hoặc số = Unit) trong các bộ của học viên. */
    public function deck(Student $student, string|int $id): ?Deck
    {
        [$kind, $mid] = Deck::parse($id);

        return $this->decks($student)->first(fn (Deck $d) => $d->kind === $kind && $d->modelId === $mid);
    }

    /** Giữ tương thích: các Unit giáo trình của học viên. */
    public function units(Student $student): Collection
    {
        return $this->decks($student)->where('kind', 'unit')->values();
    }

    /** Từ vựng của bộ thẻ (bỏ trùng). @return array<string, array> key => thẻ */
    public function words(Deck|TextbookUnit $deck): array
    {
        $deck = $deck instanceof TextbookUnit ? Deck::fromUnit($deck) : $deck;
        $zh = $deck->isChinese();
        $out = [];
        foreach ($deck->vocabulary as $v) {
            $w = trim((string) ($v['word'] ?? ''));
            if ($w === '' || mb_strlen($w) > 120) {
                continue;
            }
            $k = self::key($w);
            $out[$k] ??= [
                'key' => $k, 'deck' => $deck->id, 'unit_id' => $deck->kind === 'unit' ? $deck->modelId : null, 'word' => $w, 'pinyin' => $zh ? (string) ($v['pinyin'] ?? '') : '',
                'pos' => (string) ($v['pos'] ?? ''), 'meaning' => (string) ($v['meaning_vi'] ?? ''), 'example' => (string) ($v['example'] ?? ''),
                'lang' => $zh ? 'zh-CN' : 'en-US',
            ];
        }

        return $out;
    }

    /** Thẻ đã ôn của học viên trong các bộ, khoá "deckId:wordKey". */
    private function reviews(Student $student, Collection $decks): Collection
    {
        $units = $decks->where('kind', 'unit')->pluck('modelId');
        $lessons = $decks->where('kind', 'lesson')->pluck('modelId');
        if ($units->isEmpty() && $lessons->isEmpty()) {
            return collect();
        }

        return VocabReview::where('student_id', $student->id)
            ->where(fn ($q) => $q->whereIn('unit_id', $units)->orWhereIn('lesson_id', $lessons))->get()
            ->keyBy(fn ($r) => ($r->lesson_id ? 'l'.$r->lesson_id : 'u'.$r->unit_id).':'.$r->word_key);
    }

    /** Tình hình từng bộ: tổng từ, đã học, đến hạn, đã thuộc. */
    public function summary(Student $student): Collection
    {
        $decks = $this->decks($student);
        $reviews = $this->reviews($student, $decks);

        return $decks->map(function (Deck $d) use ($reviews) {
            $words = $this->words($d);
            $r = collect(array_keys($words))->map(fn ($k) => $reviews[$d->id.':'.$k] ?? null)->filter();

            return ['unit' => $d, 'deck' => $d, 'total' => count($words), 'learned' => $r->count(), 'due' => $r->filter(fn ($x) => $x->due_at <= now())->count(), 'mature' => $r->filter->isMature()->count()];
        });
    }

    public function dueCount(Student $student): int
    {
        return (int) $this->summary($student)->sum('due');
    }

    /** Thẻ cho 1 lượt học: thẻ đến hạn trước, sau đó từ mới (tối đa NEW_PER_SESSION). */
    public function session(Student $student, string|int|null $deckId = null): array
    {
        $decks = $this->decks($student);
        if ($deckId !== null) {
            [$kind, $mid] = Deck::parse($deckId);
            $decks = $decks->filter(fn (Deck $d) => $d->kind === $kind && $d->modelId === $mid);
        }
        $all = [];
        foreach ($decks as $d) {
            foreach ($this->words($d) as $k => $card) {
                $all[$d->id.':'.$k] = $card;
            }
        }
        $reviews = $this->reviews($student, $decks);

        $due = [];
        $new = [];
        foreach ($all as $id => $card) {
            $r = $reviews[$id] ?? null;
            if (! $r) {
                $new[] = $card + ['new' => true];
            } elseif ($r->due_at->lte(now())) {
                $due[] = $card + ['new' => false, 'due_at' => $r->due_at->timestamp];
            }
        }
        usort($due, fn ($a, $b) => $a['due_at'] <=> $b['due_at']);

        return array_merge($due, array_slice($new, 0, self::NEW_PER_SESSION));
    }

    /** Chấm 1 thẻ → lịch ôn tiếp theo (SM-2 rút gọn). */
    public function grade(Student $student, Deck|TextbookUnit $deck, string $key, string $grade): VocabReview
    {
        $deck = $deck instanceof TextbookUnit ? Deck::fromUnit($deck) : $deck;
        $word = $this->words($deck)[$key]['word'] ?? abort(404);
        $r = VocabReview::firstOrNew(['student_id' => $student->id, $deck->column() => $deck->modelId, 'word_key' => $key], ['word' => $word, 'ease' => 2.5, 'interval_days' => 0, 'reps' => 0, 'lapses' => 0]);

        $ease = (float) $r->ease;
        $interval = (int) $r->interval_days;
        $reps = (int) $r->reps;
        $due = null;

        switch ($grade) {
            case 'again':
                $ease = max(1.3, $ease - 0.2);
                $reps = 0;
                $interval = 0;
                $r->lapses = (int) $r->lapses + 1;
                $due = now()->addMinutes(10); // gặp lại trong lượt sau
                break;
            case 'hard':
                $ease = max(1.3, $ease - 0.15);
                $interval = max(1, (int) round(max(1, $interval) * 1.2));
                $reps++;
                break;
            case 'good':
                $interval = match ($reps) { 0 => 1, 1 => 3, default => max($interval + 1, (int) round($interval * $ease)) };
                $reps++;
                break;
            case 'easy':
                $interval = match ($reps) { 0 => 4, default => max($interval + 2, (int) round(max(1, $interval) * $ease * 1.3)) };
                $ease = min(3.0, $ease + 0.15);
                $reps++;
                break;
            default:
                abort(422);
        }
        $interval = min($interval, 365);

        $r->fill(['word' => $word, 'ease' => round($ease, 2), 'interval_days' => $interval, 'reps' => $reps,
            'due_at' => $due ?? now()->startOfDay()->addDays($interval)->addHours(4), 'last_reviewed_at' => now()])->save();

        return $r;
    }
}
