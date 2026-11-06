<?php

namespace App\Support\QuestionTypes\Engines;

use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\QuestionType;

/** Sắp xếp câu / đoạn / sự kiện. items ghi theo THỨ TỰ ĐÚNG; giao diện xáo trộn. */
class Ordering extends Engine
{
    public function key(): string
    {
        return 'ordering';
    }

    public function label(): string
    {
        return 'Sắp xếp thứ tự';
    }

    public function interaction(): string
    {
        return 'Danh sách thẻ có nút lên / xuống (hoặc kéo thả) để đổi thứ tự.';
    }

    public function rubric(QuestionType $type): string
    {
        return 'Điểm từng phần = tỉ lệ vị trí đặt đúng; tắt điểm từng phần thì phải đúng toàn bộ thứ tự.';
    }

    protected function contentSchema(QuestionType $type): array
    {
        return ['required' => ['items'], 'properties' => [
            'prompt' => ['type' => 'string'], 'items' => ['type' => 'array', 'items' => ['type' => 'string']],
        ]];
    }

    protected function check(array $content, QuestionType $type): array
    {
        return count($content['items'] ?? []) < 3 ? ['Cần ít nhất 3 mục để sắp xếp'] : [];
    }

    public function prepare(array $content, QuestionType $type, string $seed): array
    {
        $content['cards'] = self::shuffle($content['items'], $seed);   // giữ chỉ số gốc làm khoá
        unset($content['items']);

        return $content;
    }

    public function grade(array $content, array $answer, QuestionType $type): array
    {
        $order = array_map('intval', array_values((array) ($answer['order'] ?? [])));
        $n = count($content['items']);
        $hits = 0;
        foreach (range(0, $n - 1) as $pos) {
            $hits += ($order[$pos] ?? -1) === $pos ? 1 : 0;
        }
        $given = $order ? array_map(fn ($i) => chr(65 + $i), $order) : [];
        $part = self::part('order', $hits / $n, 1, $given ? implode('-', $given) : '', implode('-', array_map(fn ($i) => chr(65 + $i), range(0, $n - 1))));
        $part['label'] = 'Thứ tự (A, B, C… = thứ tự đúng)';

        return ['parts' => [$part], 'pending' => false];
    }

    public function perfectAnswer(array $content, QuestionType $type): array
    {
        return ['order' => range(0, count($content['items']) - 1)];
    }
}
