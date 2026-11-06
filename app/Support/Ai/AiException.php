<?php

namespace App\Support\Ai;

use RuntimeException;

/**
 * Lỗi khi gọi AI. Thông báo luôn bằng tiếng Việt, hiển thị được cho người dùng.
 * $retryable = true: lỗi tạm thời (mạng, 429, 5xx, JSON hỏng) — nên thử lại.
 */
class AiException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false, public readonly int $status = 0)
    {
        parent::__construct($message);
    }
}
