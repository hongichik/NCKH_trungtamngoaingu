<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\Matcher;
use App\Support\QuestionTypes\QuestionType;

/**
 * Tìm và sửa lỗi sai. Mỗi câu đánh dấu chỗ sai bằng *…*: "She *go* to school every day."
 * (tiếng Trung: tách từ bằng dấu cách để học viên bấm chọn được). correction = cách sửa (một hoặc nhiều đáp án).
 */
class ErrorCorrection extends Engine
{
    public function key(): string
    {
        return 'error_correction';
    }

    public function label(): string
    {
        return 'Tìm và sửa lỗi sai';
    }

    public function interaction(): string
    {
        return 'Bấm vào từ sai trong câu, rồi gõ cách sửa vào ô bên dưới.';
    }

    public function rubric(QuestionType $type): string
    {
        return $type->option('locate_only')
            ? 'Mỗi câu: bấm đúng chỗ sai = 1 ý.'
            : 'Mỗi câu 1 ý: bấm đúng chỗ sai = 0,5; sửa đúng = 0,5 (chỉ tính khi đã chọn đúng chỗ sai).';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['sentences'], 'properties' => ['sentences' => ['type' => 'array', 'items' => [
            'type' => 'object', 'required' => ['id', 'text'], 'properties' => [
                'id' => ['type' => 'string'], 'text' => ['type' => 'string'], 'correction' => ['type' => ['string', 'array']],
            ],
        ]]]];
    }

    /** @return array{tokens: list<string>, marked: list<int>} */
    public static function tokenize(string $text): array
    {
        preg_match_all('/\*[^*]+\*[^\s*]*|[^\s*]+/u', $text, $m);
        $tokens = [];
        $marked = [];
        foreach ($m[0] as $i => $t) {
            if (str_starts_with($t, '*')) {
                $marked[] = $i;
            }
            $tokens[] = str_replace('*', '', $t);
        }

        return ['tokens' => $tokens, 'marked' => $marked];
    }

    protected function check(array $content, QuestionType $type): array
    {
        $errors = [];
        foreach ($content['sentences'] ?? [] as $s) {
            if (count(self::tokenize($s['text'] ?? '')['marked']) !== 1) {
                $errors[] = "Câu {$s['id']}: cần đánh dấu đúng 1 chỗ sai bằng *…*";
            }
            if (! $type->option('locate_only') && empty($s['correction'])) {
                $errors[] = "Câu {$s['id']}: thiếu cách sửa (correction)";
            }
        }

        return $errors;
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        $content['joiner'] = $type->langOf($content) === 'zh' ? '' : ' ';
        $content['need_fix'] = ! $type->option('locate_only');
        foreach ($content['sentences'] as &$s) {
            $s['tokens'] = self::tokenize($s['text'])['tokens'];
            unset($s['text'], $s['correction']);
        }

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $lang = $type->langOf($content);
        $needFix = ! $type->option('locate_only');
        $parts = [];
        foreach ($content['sentences'] as $s) {
            ['tokens' => $tokens, 'marked' => $marked] = self::tokenize($s['text']);
            $a = (array) ($answer[$s['id']] ?? []);
            $index = isset($a['index']) && $a['index'] !== '' ? (int) $a['index'] : null;
            $fix = self::str($a['fix'] ?? '');
            $located = $index === $marked[0];
            $fixed = $located && $needFix && Matcher::check($fix, (array) $s['correction'], $lang)['correct'];
            $earned = $needFix ? ($located ? 0.5 : 0) + ($fixed ? 0.5 : 0) : ($located ? 1 : 0);
            $given = $index === null ? '' : ($tokens[$index] ?? '?').($needFix ? ' → '.$fix : '');
            $expected = $tokens[$marked[0]].($needFix ? ' → '.implode(' / ', (array) $s['correction']) : '');
            $parts[] = self::part($s['id'], $earned, 1, $given, $expected);
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return collect($content['sentences'])->mapWithKeys(fn ($s) => [$s['id'] => [
            'index' => self::tokenize($s['text'])['marked'][0], 'fix' => ((array) ($s['correction'] ?? ['']))[0],
        ]])->all();
    }
}
