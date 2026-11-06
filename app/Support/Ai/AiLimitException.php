<?php

namespace App\Support\Ai;

/** Vượt hạn mức lượt AI / ngân sách. Không thử lại tự động. */
class AiLimitException extends AiException
{
    public function __construct(string $message)
    {
        parent::__construct($message, false, 429);
    }
}
