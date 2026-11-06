<?php

namespace App\Support\Ai;

/**
 * Kết quả một lượt gọi AI kèm số liệu để tính chi phí.
 */
class AiResponse
{
    public function __construct(
        public readonly mixed $data,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly float $seconds = 0,  // chép lời: thời lượng âm thanh
        public readonly int $chars = 0,      // giọng đọc: số ký tự
    ) {}
}
