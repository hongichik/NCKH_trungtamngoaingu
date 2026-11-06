<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\QuestionType;

/** Chọn điểm trên ảnh / bản đồ: mỗi câu là một vùng tròn (x, y, r tính theo % kích thước ảnh). */
class Hotspot extends Engine
{
    public function key(): string
    {
        return 'hotspot';
    }

    public function label(): string
    {
        return 'Chọn điểm trên ảnh';
    }

    public function interaction(): string
    {
        return 'Chọn câu hỏi rồi bấm vào vị trí trên ảnh; điểm đã chọn hiện số thứ tự câu.';
    }

    public function rubric(QuestionType $type): string
    {
        return 'Mỗi câu đúng khi điểm bấm nằm trong vùng đáp án (bán kính r % ảnh).';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['image', 'questions'], 'properties' => [
            'image' => ['type' => 'string'],
            'questions' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['id', 'prompt', 'x', 'y'], 'properties' => [
                'id' => ['type' => 'string'], 'prompt' => ['type' => 'string'],
                'x' => ['type' => 'number'], 'y' => ['type' => 'number'], 'r' => ['type' => 'number'],
            ]]],
        ]];
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        foreach ($content['questions'] as &$q) {
            unset($q['x'], $q['y'], $q['r']);
        }

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $parts = [];
        foreach ($content['questions'] as $q) {
            $a = (array) ($answer[$q['id']] ?? []);
            $has = isset($a['x'], $a['y']) && is_numeric($a['x']) && is_numeric($a['y']);
            $ok = $has && hypot((float) $a['x'] - $q['x'], (float) $a['y'] - $q['y']) <= ($q['r'] ?? 6);
            $parts[] = self::part($q['id'], $ok ? 1 : 0, 1, $has ? sprintf('(%.0f%%, %.0f%%)', $a['x'], $a['y']) : '', sprintf('(%.0f%%, %.0f%%)', $q['x'], $q['y']), null, $q['prompt']);
        }

        return ['parts' => $parts, 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return collect($content['questions'])->mapWithKeys(fn ($q) => [$q['id'] => ['x' => $q['x'], 'y' => $q['y']]])->all();
    }
}
