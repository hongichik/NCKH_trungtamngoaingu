<?php

namespace App\Support\Ai;

use InvalidArgumentException;

/**
 * Đọc prompt từ resources/prompts/<tên>.txt và điền biến {{ten_bien}}.
 * Giá trị được điền đã vô hiệu hoá các thẻ <source> / <student_answer>
 * để nội dung tài liệu hay bài làm không "thoát" ra ngoài vùng dữ liệu.
 */
class Prompts
{
    private const TAGS = ['source', 'student_answer'];

    public static function path(string $name): string
    {
        if (! preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new InvalidArgumentException("Tên prompt không hợp lệ: {$name}");
        }

        return resource_path("prompts/{$name}.txt");
    }

    public static function render(string $name, array $vars = []): string
    {
        $path = self::path($name);
        if (! is_file($path)) {
            throw new InvalidArgumentException("Không tìm thấy prompt: {$name}");
        }

        $text = (string) file_get_contents($path);

        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($vars) {
            if (! array_key_exists($m[1], $vars)) {
                return $m[0];
            }
            $v = $vars[$m[1]];

            return self::escapeTags(is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }, $text);
    }

    /** Đổi "<source" → "‹source" (và tương tự) trong dữ liệu người dùng. */
    public static function escapeTags(string $value): string
    {
        foreach (self::TAGS as $tag) {
            $value = preg_replace('/<(\/?)\s*('.$tag.')/i', '‹$1$2', $value);
        }

        return $value;
    }
}
