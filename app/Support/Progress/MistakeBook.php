<?php

namespace App\Support\Progress;

use App\Models\ExerciseAttempt;
use App\Models\ExerciseSet;
use App\Models\MistakeReview;
use App\Models\Student;
use App\Support\Exercise\Scorer;
use Illuminate\Support\Collection;

/**
 * Sổ lỗi sai: các câu Đọc / Nghe học viên đã làm sai (chỉ câu đã được xem đáp án),
 * làm lại tới khi đúng thì "đã sửa". Câu Nói / Viết đoạn chấm theo tiêu chí nên không đưa vào.
 */
class MistakeBook
{
    public const TYPES = ['mcq', 'true_false', 'cloze', 'ordering', 'gap_fill'];

    /** @return Collection<string, array> key "set:code" => mục lỗi (lần sai gần nhất) */
    public function items(Student $student, bool $includeMastered = false): Collection
    {
        $attempts = ExerciseAttempt::where('student_id', $student->id)->where('status', 'graded')
            ->with(['unitTest.assignment', 'set.unit.textbook:id,language'])->latest('id')->limit(300)->get();
        $mastered = MistakeReview::where('student_id', $student->id)->whereNotNull('mastered_at')->get()
            ->keyBy(fn ($m) => $m->exercise_set_id.':'.$m->code);

        $items = collect();
        foreach ($attempts as $a) {
            if (! $a->set || ($a->unitTest?->assignment && ! $a->unitTest->assignment->answersVisible())) {
                continue; // chưa tới lúc xem đáp án
            }
            foreach ($a->result ?? [] as $r) {
                $key = $a->exercise_set_id.':'.$r['code'];
                if (! empty($r['correct']) || ! in_array($r['type'] ?? '', self::TYPES, true) || $items->has($key)) {
                    continue;
                }
                $m = $mastered[$key] ?? null;
                if ($m && $m->mastered_at->gte($a->created_at) && ! $includeMastered) {
                    continue; // đã sửa sau lần sai này
                }
                $items[$key] = $r + ['key' => $key, 'set_id' => $a->exercise_set_id, 'skill' => $a->set->skill, 'unit' => $a->set->unit?->title,
                    'language' => $a->set->unit?->textbook?->language ?? 'en', 'wrong_at' => $a->created_at, 'mastered' => (bool) $m];
            }
        }

        // Câu đã sửa xong có thể sai lại ở lần làm mới hơn → vẫn hiện (đã xử lý ở trên theo thời gian)
        return $items;
    }

    /** Chấm phần làm lại: đúng → đánh dấu đã sửa. @return array{correct: int, total: int, results: array} */
    public function retry(Student $student, array $answers): array
    {
        $items = $this->items($student);
        $scorer = app(Scorer::class);
        $results = [];
        $correct = 0;
        foreach ($answers as $key => $given) {
            $item = $items[$key] ?? null;
            if (! $item) {
                continue;
            }
            $ok = $scorer->isCorrect($item['type'], (string) $given, $item['answer'], $item['language']);
            $correct += $ok ? 1 : 0;
            $m = MistakeReview::firstOrNew(['student_id' => $student->id, 'exercise_set_id' => $item['set_id'], 'code' => $item['code']]);
            $m->tries = (int) $m->tries + 1;
            if ($ok) {
                $m->mastered_at = now();
            }
            $m->save();
            $results[$key] = ['correct' => $ok, 'given' => (string) $given, 'answer' => $item['answer'], 'explanation' => $item['explanation'] ?? ''];
        }

        return ['correct' => $correct, 'total' => count($results), 'results' => $results];
    }

    /** Ngữ cảnh để làm lại câu: bài đọc (Đọc) hoặc lời thoại (Nghe). */
    public function context(Collection $items): array
    {
        $sets = ExerciseSet::whereIn('id', $items->pluck('set_id')->unique())->get()->keyBy('id');
        $out = [];
        foreach ($sets as $id => $set) {
            $d = $set->data ?? [];
            $text = $d['passage'] ?? null;
            if (! $text && ! empty($d['script'])) {
                $text = is_array($d['script']) ? collect($d['script'])->map(fn ($l) => is_array($l) ? trim(($l['speaker'] ?? '').': '.($l['text'] ?? '')) : $l)->implode("\n") : $d['script'];
            }
            $out[$id] = ['title' => $set->title, 'text' => (string) $text, 'cloze' => (string) ($d['cloze_passage'] ?? '')];
        }

        return $out;
    }
}
