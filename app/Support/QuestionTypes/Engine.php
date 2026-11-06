<?php

namespace App\Support\QuestionTypes;

use App\Support\Ai\JsonSchema;

/**
 * Engine = một kiểu giao diện làm bài + cách chấm. Nhiều dạng bài dùng chung một engine
 * (vd IELTS True/False/Not Given, TOEIC Part 5, HSK chọn tranh đều là "choice").
 *
 * Câu trả lời của học viên luôn là một object JSON (do resources/js qtypes thu thập từ các ô có data-a).
 * Kết quả chấm: ['parts' => [[id, label, earned, max, correct, answered, given, expected, reason]], 'pending' => bool].
 */
abstract class Engine
{
    abstract public function key(): string;

    abstract public function label(): string;

    /** Mô tả giao diện làm bài (hiện ở thư viện). */
    abstract public function interaction(): string;

    /** JSON schema phần riêng của engine (gộp với instructions + stimulus chung). */
    abstract protected function contentSchema(QuestionType $type): array;

    abstract public function grade(array $content, array $answer, QuestionType $type): array;

    /** Câu trả lời đúng hoàn toàn — dùng để "xem đáp án" và test tự động. */
    abstract public function perfectAnswer(array $content, QuestionType $type): array;

    /** Mô tả cách chấm cho người xem thư viện. */
    public function rubric(QuestionType $type): string
    {
        return 'Mỗi câu đúng được 1 ý.';
    }

    public function view(): string
    {
        return 'question-types.engines.'.str_replace('_', '-', $this->key());
    }

    public function schema(QuestionType $type): array
    {
        $own = $this->contentSchema($type);
        $own['properties'] = ['instructions' => ['type' => 'string'], 'language' => ['type' => 'string', 'enum' => ['en', 'zh']], 'stimulus' => self::stimulusSchema()] + $own['properties'];

        return ['type' => 'object'] + $own;
    }

    /** @return list<string> */
    public function validate(array $content, QuestionType $type): array
    {
        $errors = JsonSchema::validate($content, $this->schema($type));
        foreach (['questions', 'items', 'sentences'] as $list) {
            if (array_key_exists($list, $content) && $content[$list] === []) {
                $errors[] = "\$.{$list}: cần ít nhất 1 mục";
            }
        }

        return array_merge($errors, $this->check($content, $type));
    }

    /** Kiểm tra thêm ngoài schema (đáp án trỏ đúng lựa chọn, đủ ô trống…). */
    protected function check(array $content, QuestionType $type): array
    {
        return [];
    }

    /** Dữ liệu cho giao diện (xáo trộn, tách token…) — KHÔNG được chứa đáp án. */
    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        return $content;
    }

    // ---------------------------------------------------------------- tiện ích dùng chung

    public static function stimulusSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'title' => ['type' => 'string'],
            'passage' => ['type' => 'string'],
            'image' => ['type' => 'string'],
            'audio' => ['type' => 'string'],
            'transcript' => ['type' => 'string'],
            'markers' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['key', 'x', 'y'], 'properties' => [
                'key' => ['type' => 'string'], 'x' => ['type' => 'number'], 'y' => ['type' => 'number'],
            ]]],
        ]];
    }

    /** Đường dẫn ảnh / audio trong nội dung: "/images/…" → URL đầy đủ của site, URL ngoài giữ nguyên. */
    public static function url(?string $path): string
    {
        return $path && str_starts_with($path, '/') ? asset(ltrim($path, '/')) : (string) $path;
    }

    /**
     * Lựa chọn dạng chuỗi → [{key: A, text}], dạng object giữ nguyên.
     * Chữ đã ghi sẵn nhãn trùng khoá ("A. Six months", "(B) two", "c) …") thì bỏ nhãn đó để không hiện "A. A. …".
     */
    public static function options(array $options): array
    {
        $out = [];
        foreach (array_values($options) as $i => $o) {
            $o = is_array($o) ? ['key' => (string) $o['key'], 'text' => (string) ($o['text'] ?? $o['key'])] : ['key' => chr(65 + $i), 'text' => (string) $o];
            $o['text'] = self::stripLabel($o['text'], $o['key']);
            $out[] = $o;
        }

        return $out;
    }

    /** Bỏ nhãn ở đầu chữ nếu trùng khoá: "A. text" / "A) text" / "(A) text" / "a. text" với khoá A. */
    public static function stripLabel(string $text, string $key): string
    {
        $k = preg_quote($key, '/');
        $stripped = preg_replace('/^\s*\(?'.$k.'\s*[.):]\s+/iu', '', $text, 1);

        return trim($stripped) === '' ? $text : $stripped;
    }

    /** Xáo trộn ổn định theo seed (cùng bài → cùng thứ tự, không phụ thuộc mt_rand toàn cục). */
    public static function shuffle(array $items, string $seed): array
    {
        $keys = array_keys($items);
        usort($keys, fn ($a, $b) => crc32($seed.'|'.$a) <=> crc32($seed.'|'.$b));
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $items[$k];
        }
        // Tránh trường hợp xáo xong vẫn y nguyên thứ tự đúng.
        if (count($out) > 1 && array_keys($out) === array_keys($items)) {
            $out = array_reverse($out, true);
        }

        return $out;
    }

    /** Tách văn bản có ô trống [[id]] thành các đoạn: ['t' => text] | ['gap' => id]. */
    public static function segments(string $text): array
    {
        $parts = preg_split('/\[\[([A-Za-z0-9_-]+)\]\]/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = [];
        foreach ($parts as $i => $p) {
            $out[] = $i % 2 ? ['gap' => $p] : ['t' => $p];
        }

        return $out;
    }

    public static function gapIds(string $text): array
    {
        preg_match_all('/\[\[([A-Za-z0-9_-]+)\]\]/u', $text, $m);

        return $m[1];
    }

    protected static function part(string $id, float $earned, float $max, mixed $given, mixed $expected, ?string $reason = null, ?string $label = null): array
    {
        $answered = ! ($given === null || $given === '' || $given === []);

        return [
            'id' => $id, 'label' => $label ?? $id, 'earned' => round($earned, 3), 'max' => $max,
            'correct' => $max > 0 ? $earned >= $max : $earned >= 0, 'answered' => $answered,
            'given' => is_array($given) ? implode(', ', array_map(fn ($g) => is_array($g) ? json_encode($g, JSON_UNESCAPED_UNICODE) : (string) $g, $given)) : (string) ($given ?? ''),
            'expected' => is_array($expected) ? implode(' / ', $expected) : (string) $expected, 'reason' => $reason,
        ];
    }

    protected static function str(mixed $v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }
}
