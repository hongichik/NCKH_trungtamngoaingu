<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\Matcher;
use App\Support\QuestionTypes\QuestionType;

/**
 * Viết đoạn văn / bài luận / thư. Chấm theo tiêu chí (tuỳ chọn "criteria" của dạng bài, vd 4 tiêu chí IELTS)
 * bằng AI hoặc giáo viên — kết quả máy chấm ngay chỉ kiểm tra số từ và để "Chờ chấm".
 */
class Writing extends Engine
{
    public const DEFAULT_CRITERIA = ['Hoàn thành yêu cầu', 'Mạch lạc, liên kết', 'Từ vựng', 'Ngữ pháp'];

    public function key(): string
    {
        return 'writing';
    }

    public function label(): string
    {
        return 'Viết đoạn / bài luận';
    }

    public function interaction(): string
    {
        return 'Ô soạn thảo nhiều dòng, đếm từ (tiếng Trung đếm chữ Hán), đồng hồ đếm ngược nếu có giới hạn thời gian.';
    }

    public function criteria(QuestionType $type): array
    {
        return $type->option('criteria', self::DEFAULT_CRITERIA);
    }

    public function rubric(QuestionType $type): string
    {
        return ($type->grading === 'teacher' ? 'Giáo viên' : 'AI').' chấm theo '.count($this->criteria($type)).' tiêu chí: '.implode(' · ', $this->criteria($type)).'. Bài thiếu số từ tối thiểu bị đánh dấu.';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['prompt'], 'properties' => [
            'prompt' => ['type' => 'string'], 'min_words' => ['type' => 'integer'], 'max_words' => ['type' => 'integer'],
            'time_minutes' => ['type' => 'integer'], 'ideas' => ['type' => 'array', 'items' => ['type' => 'string']],
            'useful_language' => ['type' => 'array', 'items' => ['type' => 'string']], 'sample_answer' => ['type' => 'string'],
            'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
        ]];
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        unset($content['sample_answer']);
        $content['criteria'] = $this->criteria($type);

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $lang = $type->langOf($content);
        $text = self::str($answer['text'] ?? '');
        $words = Matcher::wordCount($text, $lang);
        $min = (int) ($content['min_words'] ?? 0);
        $reason = $text === '' ? 'Bỏ trống' : ($min && $words < $min ? "Thiếu từ: {$words}/{$min}" : "{$words} ".($lang === 'zh' ? 'chữ' : 'từ'));
        $missing = array_filter($content['keywords'] ?? [], fn ($k) => ! str_contains(mb_strtolower($text), mb_strtolower($k)));
        if ($text !== '' && $missing) {
            $reason .= ' · chưa dùng: '.implode(', ', $missing);
        }
        $parts = array_map(fn ($c, $i) => self::part('c'.($i + 1), 0, 1, $i === 0 ? mb_strimwidth($text, 0, 80, '…') : '', 'Chờ chấm', $i === 0 ? $reason : null, $c), $this->criteria($type), array_keys($this->criteria($type)));

        return ['parts' => $parts, 'pending' => $text !== '', 'words' => $words];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return ['text' => ($content['sample_answer'] ?? '') ?: '(Bài mẫu chưa có — đây là câu trả lời giả lập để thử luồng chấm.)'];
    }
}
