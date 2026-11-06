<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\Matcher;
use App\Support\QuestionTypes\QuestionType;

/** Nghe chép chính tả: nghe câu (file audio hoặc giọng đọc máy) rồi gõ lại. Điểm từng phần theo tỉ lệ từ đúng. */
class Dictation extends Engine
{
    public function key(): string
    {
        return 'dictation';
    }

    public function label(): string
    {
        return 'Nghe chép chính tả';
    }

    public function interaction(): string
    {
        return 'Nút nghe (nghe lại được, chỉnh tốc độ) và ô gõ lại câu nghe được.';
    }

    public function rubric(QuestionType $type): string
    {
        return 'Mỗi câu 1 ý; điểm từng phần = tỉ lệ từ đúng (so theo khoảng cách chỉnh sửa trên từ, tiếng Trung tính theo chữ). Đúng ≥ 95% tính là đúng hẳn.';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['questions'], 'properties' => ['questions' => ['type' => 'array', 'items' => [
            'type' => 'object', 'required' => ['id', 'text'], 'properties' => [
                'id' => ['type' => 'string'], 'text' => ['type' => 'string'], 'audio' => ['type' => 'string'],
            ],
        ]]]];
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        // Lời đọc vẫn phải gửi xuống để trình duyệt đọc khi không có file audio — chỉ dùng cho luyện tập, không dùng cho bài thi.
        foreach ($content['questions'] as &$q) {
            $q['speak'] = empty($q['audio']) ? base64_encode($q['text']) : null;
            unset($q['text']);
        }

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $lang = $type->langOf($content);
        $parts = [];
        foreach ($content['questions'] as $q) {
            $given = self::str($answer[$q['id']] ?? '');
            $acc = $given === '' ? 0 : Matcher::wordAccuracy($given, $q['text'], $lang);
            $parts[] = self::part($q['id'], $acc >= 0.95 ? 1 : round($acc, 2), 1, $given, $q['text']);
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return collect($content['questions'])->mapWithKeys(fn ($q) => [$q['id'] => $q['text']])->all();
    }
}
