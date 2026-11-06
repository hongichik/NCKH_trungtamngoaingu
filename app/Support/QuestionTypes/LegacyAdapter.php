<?php

namespace App\Support\QuestionTypes;

use App\Models\Question;
use App\Support\Exercise\Answers;

/**
 * Chuyển câu hỏi V1 (bảng questions) sang nội dung theo dạng bài của thư viện, để hiển thị / chấm bằng thư viện
 * mà KHÔNG đổi dữ liệu cũ. Bài V1 vẫn được App\Support\Exercise\Scorer chấm như trước; test đảm bảo hai cách cho cùng kết quả.
 */
class LegacyAdapter
{
    /** @return array{type: QuestionType, content: array, answer: array}  answer = câu trả lời V1 đổi sang dạng của thư viện */
    public static function convert(Question $q, string $given = '', string $language = 'en'): array
    {
        $type = Registry::forLegacy($q->type) ?? throw new \InvalidArgumentException("Loại V1 chưa có trong thư viện: {$q->type}");
        $id = (string) $q->code;
        $meta = $q->meta ?? [];
        $base = ['language' => $language];

        [$content, $answer] = match ($q->type) {
            'mcq', 'cloze' => [
                $base + ['questions' => [['id' => $id, 'prompt' => $q->prompt, 'options' => self::stripLetters($q->options ?? []), 'answer' => Answers::letter($q->answer)]]],
                [$id => $given === '' ? '' : Answers::letter($given)],
            ],
            'true_false' => [
                $base + ['questions' => [['id' => $id, 'prompt' => $q->prompt, 'answer' => ucfirst(strtolower(trim($q->answer)))]]],
                [$id => $given === '' ? '' : ucfirst(strtolower(trim($given)))],
            ],
            'ordering' => self::ordering($q, $given, $base),
            'gap_fill' => [
                $base + ['text' => $q->prompt."\n→ [[{$id}]]", 'answers' => [$id => [$q->answer]]],
                [$id => $given],
            ],
            'transformation' => [
                $base + ['questions' => [['id' => $id, 'prompt' => $q->prompt, 'start' => $meta['start'] ?? '', 'answers' => array_values(array_filter(array_merge([$q->answer], $meta['accepted_variants'] ?? [])))]]],
                [$id => $given],
            ],
            'paragraph' => [
                $base + ['prompt' => $q->prompt, 'min_words' => (int) ($meta['min_words'] ?? 0), 'max_words' => (int) ($meta['max_words'] ?? 0), 'sample_answer' => (string) ($meta['sample_answer'] ?? '')],
                ['text' => $given],
            ],
            'speaking' => [$base + ['prompt' => $q->prompt, 'speak_seconds' => (int) ($meta['time_seconds'] ?? 60)], ['recorded' => $given !== '']],
            'read_aloud' => [$base + ['read_text' => $q->prompt, 'speak_seconds' => (int) ($meta['time_seconds'] ?? 30)], ['recorded' => $given !== '']],
        };

        return ['type' => $type, 'content' => array_filter($content, fn ($v) => $v !== ''), 'answer' => $answer];
    }

    /** "A. text" → "text" (thư viện tự gắn chữ cái theo thứ tự). */
    private static function stripLetters(array $options): array
    {
        return array_map(fn ($o) => preg_replace('/^\(?[A-Ha-h][.):]\s*/', '', (string) $o), $options);
    }

    /** V1: options "a. …", đáp án "c-a-b" → items theo thứ tự đúng; câu trả lời → danh sách chỉ số. */
    private static function ordering(Question $q, string $given, array $base): array
    {
        $byLetter = [];
        foreach ($q->options ?? [] as $o) {
            $byLetter[Answers::letter((string) $o)] = preg_replace('/^\(?[A-Ha-h][.):]\s*/', '', (string) $o);
        }
        $right = str_split(Answers::ordering($q->answer));
        $items = array_map(fn ($l) => $byLetter[$l] ?? $l, $right);
        $pos = array_flip($right);
        $order = $given === '' ? [] : array_map(fn ($l) => $pos[$l] ?? -1, str_split(Answers::ordering($given)));

        return [$base + ['prompt' => $q->prompt, 'items' => $items], ['order' => $order]];
    }
}
