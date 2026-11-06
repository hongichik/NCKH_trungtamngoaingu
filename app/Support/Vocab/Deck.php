<?php

namespace App\Support\Vocab;

use App\Models\CourseLesson;
use App\Models\TextbookUnit;

/**
 * Một bộ thẻ từ vựng: Unit giáo trình (V1, id "u{id}") hoặc bài học của khoá (id "l{id}").
 * Id dạng số (không tiền tố) được hiểu là Unit — giữ tương thích link / dữ liệu cũ.
 */
class Deck
{
    public function __construct(
        public string $id,
        public string $kind,          // unit | lesson
        public int $modelId,
        public string $title,
        public string $subtitle,
        public string $language,      // en | zh
        public array $vocabulary,     // [{word, pinyin, pos, meaning_vi, example}]
    ) {}

    public static function fromUnit(TextbookUnit $u): self
    {
        return new self('u'.$u->id, 'unit', $u->id, (string) $u->title, (string) ($u->textbook?->title ?? ''), $u->textbook?->language === 'zh' ? 'zh' : 'en', $u->profile?->data['vocabulary'] ?? []);
    }

    public static function fromLesson(CourseLesson $l): self
    {
        return new self('l'.$l->id, 'lesson', $l->id, (string) $l->title, (string) ($l->course?->title ?? ''), $l->course?->language === 'zh' ? 'zh' : 'en', $l->vocabulary ?? []);
    }

    /** "u12" | "l34" | "12" → [kind, id] */
    public static function parse(string|int $id): array
    {
        $id = (string) $id;

        return str_starts_with($id, 'l') ? ['lesson', (int) substr($id, 1)] : ['unit', (int) ltrim($id, 'u')];
    }

    public function isChinese(): bool
    {
        return $this->language === 'zh';
    }

    /** Cột khoá của bảng vocab_reviews. */
    public function column(): string
    {
        return $this->kind === 'lesson' ? 'lesson_id' : 'unit_id';
    }
}
