<?php

namespace App\Support\QuestionTypes;

/**
 * Một dạng bài trong thư viện (khai báo trong code ở Catalog/*).
 * Mỗi dạng dùng một "engine" (giao diện làm bài + cách chấm) với tuỳ chọn riêng,
 * cộng thêm cách tính điểm mặc định và dữ liệu mẫu.
 */
class QuestionType
{
    public const LANGS = ['any' => 'Mọi ngôn ngữ', 'en' => 'Tiếng Anh', 'zh' => 'Tiếng Trung'];

    public const SKILLS = [
        'reading' => 'Đọc', 'listening' => 'Nghe', 'writing' => 'Viết', 'speaking' => 'Nói',
        'vocab' => 'Từ vựng', 'grammar' => 'Ngữ pháp', 'pronunciation' => 'Phát âm',
    ];

    public const STANDARDS = [
        'general' => 'Chung', 'ielts' => 'IELTS', 'toeic' => 'TOEIC', 'cambridge' => 'Cambridge (KET/PET/FCE)',
        'vstep' => 'VSTEP', 'pronunciation' => 'Phát âm tiếng Anh', 'hsk' => 'HSK 1–6', 'hskk' => 'HSKK',
        'zh' => 'Riêng tiếng Trung', 'v1' => 'Dạng bài V1',
    ];

    public const GRADING = ['auto' => 'Máy chấm ngay', 'ai' => 'AI chấm theo tiêu chí', 'teacher' => 'Giáo viên chấm'];

    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $lang,
        public readonly string $skill,
        public readonly string $standard,
        public readonly string $engine,
        public readonly array $sample,
        public readonly array $options = [],
        public readonly array $scoring = [],
        public readonly string $grading = 'auto',
        public readonly string $description = '',
        public readonly ?string $legacy = null,
    ) {}

    public static function make(array $d): self
    {
        return new self(
            code: $d['code'], name: $d['name'], lang: $d['lang'] ?? 'any', skill: $d['skill'],
            standard: $d['standard'], engine: $d['engine'], sample: $d['sample'],
            options: $d['options'] ?? [], scoring: $d['scoring'] ?? [], grading: $d['grading'] ?? 'auto',
            description: $d['description'] ?? '', legacy: $d['legacy'] ?? null,
        );
    }

    /** Ngôn ngữ thực tế của một nội dung (dạng "mọi ngôn ngữ" có thể ghi language trong nội dung). */
    public function langOf(array $content): string
    {
        return $content['language'] ?? ($this->lang === 'any' ? 'en' : $this->lang);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function engine(): Engine
    {
        return Engines::get($this->engine);
    }

    public function label(string $field): string
    {
        return match ($field) {
            'lang' => self::LANGS[$this->lang] ?? $this->lang,
            'skill' => self::SKILLS[$this->skill] ?? $this->skill,
            'standard' => self::STANDARDS[$this->standard] ?? $this->standard,
            'grading' => self::GRADING[$this->grading] ?? $this->grading,
        };
    }
}
