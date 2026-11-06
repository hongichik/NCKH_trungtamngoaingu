<?php

namespace App\Support\Exercise;

use App\Models\ExerciseAttempt;
use App\Models\Question;
use App\Support\Ai\Ai;
use App\Support\Textbook\Text;
use Illuminate\Support\Facades\Storage;

/**
 * Chấm bài Nói / Viết (chạy nền sau khi học viên nộp):
 *  - Viết lại câu: so khớp sau chuẩn hoá với đáp án + biến thể; không khớp mới hỏi AI (gộp 1 lượt).
 *  - Đoạn văn: kiểm tra bằng code (độ dài, ngôn ngữ) → AI chấm theo rubric → máy chủ kẹp điểm.
 *  - Nói (3 lớp): AI chép lời → kiểm tra bằng code (quá ngắn, không đúng ngôn ngữ, tốc độ lạ) → AI chấm 5 tiêu chí → kẹp điểm.
 *  - Đọc to: AI chép lời → code so từng từ / chữ với câu mẫu.
 */
class OpenGrader
{
    /** Giới hạn điểm tối đa theo trạng thái. */
    private const CAP = ['ok' => 10, 'off_topic' => 3, 'unclear' => 3, 'not_target_language' => 2, 'too_short' => 0];

    public function grade(ExerciseAttempt $attempt): void
    {
        $attempt->loadMissing('set.questions', 'set.unit.textbook', 'set.unit.profile', 'student');
        $set = $attempt->set;
        $lang = $set->unit->textbook->language;
        $ai = app(Ai::class)->for($attempt->student);
        $answers = $attempt->answers ?? [];
        $files = $attempt->files ?? [];
        $keywords = array_slice(array_column($set->unit->profile?->data['vocabulary'] ?? [], 'word'), 0, 20);

        $items = [];
        $toJudge = [];
        foreach ($set->questions as $q) {
            $given = trim((string) ($answers[$q->code] ?? ''));
            $item = ['code' => $q->code, 'type' => $q->type, 'prompt' => $q->prompt, 'options' => [], 'given' => $given,
                'answer' => $q->answer, 'explanation' => $q->explanation, 'evidence' => null, 'meta' => $q->meta ?? []];

            $item += match ($q->type) {
                'transformation' => $this->transformation($q, $given, $lang, $toJudge),
                'paragraph' => $this->paragraph($ai, $q, $given, $lang, $set),
                'speaking' => $this->speaking($ai, $q, $files[$q->code] ?? null, $lang, $set, $keywords),
                'read_aloud' => $this->readAloud($ai, $q, $files[$q->code] ?? null, $lang),
                default => ['correct' => false, 'score' => 0.0],
            };
            $items[$q->code] = $item;
        }

        if ($toJudge) {
            $this->judgeTransformations($ai, $toJudge, $items, $lang);
        }

        $items = array_values($items);
        $total = count($items);
        $avg = $total ? array_sum(array_column($items, 'score')) / $total : 0;

        $attempt->update([
            'result' => $items,
            'correct' => count(array_filter($items, fn ($i) => $i['score'] >= 5)),
            'total' => $total,
            'score' => round($avg, 1),
            'status' => 'graded',
            'error' => null,
        ]);

        // Ghi âm: bài tự luyện bình thường xoá ngay; bài được giao (giáo viên chấm lại) hoặc cần xem lại thì giữ.
        $keepAll = (bool) $attempt->unitTest?->assignment_id;
        foreach ($items as $i) {
            if (isset($files[$i['code']]['path']) && ! $keepAll && empty($i['needs_review'])) {
                Storage::disk('local')->delete($files[$i['code']]['path']);
            }
        }
    }

    // ------------------------------------------------------------------ Viết

    private function transformation(Question $q, string $given, string $lang, array &$toJudge): array
    {
        if ($given === '') {
            return ['correct' => false, 'score' => 0.0, 'comment_vi' => 'Chưa làm câu này.'];
        }
        $ok = collect([$q->answer, ...($q->meta['accepted_variants'] ?? [])])
            ->contains(fn ($a) => Answers::normalize($a, $lang) === Answers::normalize($given, $lang));
        if ($ok) {
            return ['correct' => true, 'score' => 10.0];
        }
        $toJudge[] = ['id' => $q->code, 'original' => $q->prompt, 'start' => $q->meta['start'] ?? '', 'model_answer' => $q->answer, 'student' => $given];

        return ['correct' => false, 'score' => 0.0];
    }

    private function judgeTransformations(Ai $ai, array $toJudge, array &$items, string $lang): void
    {
        $s = ['type' => 'string'];
        $r = $ai->json('grading', 'judge_transformation', [
            'language' => $this->langName($lang),
            'items_json' => json_encode($toJudge, JSON_UNESCAPED_UNICODE),
        ], ['type' => 'object', 'properties' => ['items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'id' => $s, 'correct' => ['type' => 'boolean'], 'comment_vi' => $s,
        ], 'required' => ['id', 'correct', 'comment_vi']]]], 'required' => ['items']], 'judge_transformation', ['timeout' => 60]);

        foreach ($r['items'] as $j) {
            if (isset($items[$j['id']])) {
                $items[$j['id']]['correct'] = (bool) $j['correct'];
                $items[$j['id']]['score'] = $j['correct'] ? 10.0 : 0.0;
                $items[$j['id']]['comment_vi'] = $j['comment_vi'];
            }
        }
    }

    private function paragraph(Ai $ai, Question $q, string $text, string $lang, $set): array
    {
        $m = $q->meta ?? [];
        $words = Text::size($text, $lang);
        $min = (int) ($m['min_words'] ?? 0);

        if ($words < 5) {
            return ['correct' => false, 'score' => 0.0, 'status' => 'unclear', 'feedback_vi' => 'Bài viết quá ngắn. Hãy viết đủ khoảng '.$min.' '.$this->unitWord($lang).'.'];
        }
        if ($this->vietnameseRatio($text) > 0.5) {
            return ['correct' => false, 'score' => 0.0, 'status' => 'not_target_language', 'feedback_vi' => 'Bài viết chủ yếu bằng tiếng Việt. Hãy viết bằng '.$this->langNameVi($lang).', dùng các cụm từ gợi ý nhé.'];
        }

        $s = ['type' => 'string'];
        $g = $ai->json('grading', 'grade_writing', [
            'language' => $this->langName($lang), 'level' => $set->unit->textbook->level ?: 'as in the textbook',
            'task' => $q->prompt, 'min_words' => $min, 'max_words' => (int) ($m['max_words'] ?? 0),
            'unit_word' => $lang === 'zh' ? 'characters' : 'words', 'word_count' => $words, 'student_text' => $text,
        ], ['type' => 'object', 'properties' => [
            'status' => $s,
            'scores' => ['type' => 'object', 'properties' => ['task_achievement' => ['type' => 'number'], 'coherence' => ['type' => 'number'], 'vocabulary' => ['type' => 'number'], 'grammar' => ['type' => 'number']], 'required' => ['task_achievement', 'coherence', 'vocabulary', 'grammar']],
            'overall' => ['type' => 'number'],
            'errors' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['original' => $s, 'correction' => $s, 'explanation_vi' => $s], 'required' => ['original', 'correction', 'explanation_vi']]],
            'strengths_vi' => ['type' => 'array', 'items' => $s], 'feedback_vi' => $s, 'corrected_text' => $s,
            'flags' => ['type' => 'array', 'items' => $s],
        ], 'required' => ['status', 'scores', 'overall', 'errors', 'strengths_vi', 'feedback_vi', 'corrected_text', 'flags']], 'grade_writing', ['timeout' => 90]);

        $g = $this->enforce($g);
        if ($min && $words < $min * 0.6) {
            $g['overall'] = min($g['overall'], 5.0); // quá ngắn so với yêu cầu
            $g['feedback_vi'] .= ' Bài viết còn ngắn ('.$words.'/'.$min.' '.$this->unitWord($lang).').';
        }

        return $g + ['correct' => $g['overall'] >= 5, 'score' => $g['overall'], 'word_count' => $words, 'needs_review' => $g['status'] !== 'ok' || ! empty($g['flags'])];
    }

    // ------------------------------------------------------------------ Nói

    private function speaking(Ai $ai, Question $q, ?array $file, string $lang, $set, array $keywords): array
    {
        if (! $file) {
            return ['correct' => false, 'score' => 0.0, 'status' => 'too_short', 'feedback_vi' => 'Chưa ghi âm câu trả lời.'];
        }
        $duration = (float) ($file['duration'] ?? 0);

        // Lớp 1: chép lời (giữ nguyên lỗi, có cả tiếng Việt để phát hiện nói tiếng Việt)
        $tr = $ai->transcribe(Storage::disk('local')->path($file['path']), [
            'languages' => [$lang, 'vi'], 'keywords' => $keywords, 'duration' => $duration,
            'prompt' => 'A Vietnamese learner answering a '.$this->langName($lang).' speaking task about '.$set->unit->title.'. Transcribe verbatim, including hesitations (uh, um), repetitions and mistakes. Do not correct the learner.',
        ]);
        $transcript = trim($tr['text']);

        // Lớp 2: kiểm tra bằng code (không tốn AI)
        $clean = preg_replace('/\b(uh|um|er|ah|hmm)\b/iu', '', $transcript);
        $size = Text::size($clean, $lang);
        $wpm = $duration > 0 ? Text::size($transcript, $lang) / $duration * 60 : 0;
        $flags = ($wpm > ($lang === 'zh' ? 400 : 220) || ($duration > 30 && $wpm < 15)) ? ['check_audio'] : [];
        $base = ['transcript' => $transcript, 'duration' => round($duration), 'wpm' => round($wpm), 'sample_answer' => $q->meta['sample_answer'] ?? ''];

        if ($duration < 5 || $size < ($lang === 'zh' ? 8 : 5)) {
            return $base + ['correct' => false, 'score' => 0.0, 'status' => 'too_short', 'feedback_vi' => 'Bài nói quá ngắn hoặc không nghe rõ. Hãy nói ít nhất vài câu nhé!', 'flags' => $flags, 'needs_review' => (bool) $flags];
        }
        if (($tr['language'] ?? null) === 'vietnamese' || $this->vietnameseRatio($transcript) > 0.5) {
            return $base + ['correct' => false, 'score' => 0.0, 'status' => 'not_target_language', 'feedback_vi' => 'Bài nói chủ yếu bằng tiếng Việt. Hãy trả lời bằng '.$this->langNameVi($lang).', dùng các cụm từ gợi ý nhé.', 'flags' => $flags, 'needs_review' => true];
        }

        // Lớp 3: AI chấm theo rubric, máy chủ kẹp điểm
        $s = ['type' => 'string'];
        $g = $ai->json('grading', 'grade_speaking', [
            'language' => $this->langName($lang), 'level' => $set->unit->textbook->level ?: 'as in the textbook',
            'task' => $q->prompt, 'duration_seconds' => round($duration), 'transcript' => $transcript,
            'tone_note' => $lang === 'zh' ? ' (including tones)' : '',
        ], ['type' => 'object', 'properties' => [
            'status' => $s,
            'scores' => ['type' => 'object', 'properties' => ['task_achievement' => ['type' => 'number'], 'pronunciation' => ['type' => 'number'], 'fluency' => ['type' => 'number'], 'vocabulary' => ['type' => 'number'], 'grammar' => ['type' => 'number']], 'required' => ['task_achievement', 'pronunciation', 'fluency', 'vocabulary', 'grammar']],
            'overall' => ['type' => 'number'],
            'possible_pronunciation_issues' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['heard_as' => $s, 'likely_word' => $s, 'tip_vi' => $s], 'required' => ['heard_as', 'likely_word', 'tip_vi']]],
            'strengths_vi' => ['type' => 'array', 'items' => $s], 'feedback_vi' => $s, 'next_step_vi' => $s, 'better_answer' => $s,
            'flags' => ['type' => 'array', 'items' => $s],
        ], 'required' => ['status', 'scores', 'overall', 'possible_pronunciation_issues', 'strengths_vi', 'feedback_vi', 'next_step_vi', 'better_answer', 'flags']], 'grade_speaking', ['timeout' => 90]);

        $g = $this->enforce($g);
        $g['flags'] = array_values(array_unique(array_merge($g['flags'] ?? [], $flags)));

        return $base + $g + ['correct' => $g['overall'] >= 5, 'score' => $g['overall'], 'needs_review' => $g['status'] !== 'ok' || ! empty($g['flags'])];
    }

    private function readAloud(Ai $ai, Question $q, ?array $file, string $lang): array
    {
        if (! $file) {
            return ['correct' => false, 'score' => 0.0, 'status' => 'too_short', 'feedback_vi' => 'Chưa ghi âm.'];
        }
        $tr = $ai->transcribe(Storage::disk('local')->path($file['path']), [
            'languages' => [$lang], 'duration' => (float) ($file['duration'] ?? 0),
            'prompt' => 'A Vietnamese learner reading this sentence aloud: "'.$q->prompt.'". Transcribe exactly what is heard, do not correct.',
        ]);
        $align = $this->align($q->prompt, $tr['text'], $lang);
        $score = $align['total'] ? round($align['matched'] / $align['total'] * 10, 1) : 0.0;

        return [
            'correct' => $score >= 5, 'score' => $score, 'transcript' => trim($tr['text']), 'words' => $align['words'],
            'feedback_vi' => $score >= 9 ? 'Đọc rất chuẩn!' : ($score >= 6 ? 'Khá tốt — luyện lại các từ màu đỏ.' : 'Hãy nghe mẫu rồi đọc chậm, rõ từng từ.'),
        ];
    }

    /**
     * So từng từ (tiếng Anh) / từng chữ (tiếng Trung) của câu mẫu với lời chép (dãy con chung dài nhất).
     *
     * @return array{words: list<array{t: string, ok: bool}>, matched: int, total: int}
     */
    public function align(string $target, string $heard, string $lang): array
    {
        $split = fn ($s) => $lang === 'zh'
            ? preg_split('//u', preg_replace('/[\p{P}\p{S}\s]|[^\p{Han}A-Za-z0-9]/u', '', $s), -1, PREG_SPLIT_NO_EMPTY)
            : preg_split('/\s+/u', trim(preg_replace("/[^\p{L}\p{N}'\s]/u", ' ', mb_strtolower($s))), -1, PREG_SPLIT_NO_EMPTY);
        $display = $lang === 'zh' ? $split($target) : preg_split('/\s+/u', trim($target), -1, PREG_SPLIT_NO_EMPTY);
        $a = $split($target);
        $b = $split($heard);
        if ($lang !== 'zh') {
            // "campsite" ↔ "camp site", "well-known" ↔ "well known": tách từ ghép trong lời chép theo cách viết của câu mẫu
            $pairs = [];
            for ($i = 0; $i < count($a) - 1; $i++) {
                $pairs[$a[$i].$a[$i + 1]] = [$a[$i], $a[$i + 1]];
            }
            $expanded = [];
            foreach ($b as $w) {
                $key = str_replace(['-', "'"], '', $w);
                array_push($expanded, ...($pairs[$key] ?? [$w]));
            }
            $b = $expanded;
            $a = array_map(fn ($w) => str_replace('-', '', $w), $a);
        }
        $n = count($a);
        $m = count($b);

        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = $a[$i] === $b[$j] ? $dp[$i + 1][$j + 1] + 1 : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }
        $ok = array_fill(0, $n, false);
        for ($i = 0, $j = 0; $i < $n && $j < $m;) {
            if ($a[$i] === $b[$j]) {
                $ok[$i] = true;
                $i++;
                $j++;
            } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        $words = [];
        foreach ($a as $i => $w) {
            $words[] = ['t' => $display[$i] ?? $w, 'ok' => $ok[$i]];
        }

        return ['words' => $words, 'matched' => count(array_filter($ok)), 'total' => $n];
    }

    // ------------------------------------------------------------------

    /** Kẹp điểm 0–10; giới hạn theo trạng thái (lạc đề ≤ 3, sai ngôn ngữ ≤ 2...). */
    private function enforce(array $g): array
    {
        $clamp = fn ($v) => is_numeric($v) ? max(0, min(10, (float) $v)) : 0.0;
        foreach ($g['scores'] as $k => $v) {
            $g['scores'][$k] = $clamp($v);
        }
        if ($g['status'] === 'off_topic') {
            $g['scores']['task_achievement'] = 0;
        }
        $g['overall'] = round(min($clamp($g['overall']), self::CAP[$g['status']] ?? 10), 1);

        return $g;
    }

    /** Tỉ lệ từ có dấu tiếng Việt (phát hiện bài làm bằng tiếng Việt). */
    public function vietnameseRatio(string $s): float
    {
        $words = array_filter(preg_split('/\s+/u', mb_strtolower($s)), fn ($w) => preg_match('/\p{L}/u', $w));
        if (! $words) {
            return 0;
        }
        $common = ['không', 'của', 'và', 'là', 'có', 'em', 'tôi', 'cho', 'những', 'này', 'thì', 'mà', 'với', 'các', 'được', 'ạ', 'nha', 'ơi'];
        $vi = count(array_filter($words, fn ($w) => preg_match('/[ăâđêôơưáàảãạấầẩẫậắằẳẵặéèẻẽẹếềểễệíìỉĩịóòỏõọốồổỗộớờởỡợúùủũụứừửữựýỳỷỹỵ]/u', $w) || in_array($w, $common, true)));

        return $vi / count($words);
    }

    private function langName(string $lang): string
    {
        return $lang === 'zh' ? 'Chinese (Mandarin)' : 'English';
    }

    private function langNameVi(string $lang): string
    {
        return $lang === 'zh' ? 'tiếng Trung' : 'tiếng Anh';
    }

    private function unitWord(string $lang): string
    {
        return $lang === 'zh' ? 'chữ' : 'từ';
    }
}
