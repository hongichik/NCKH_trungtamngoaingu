<?php

namespace App\Support\Ai\Jobs;

use App\Models\AiJob;
use App\Support\Ai\Ai;

/**
 * Việc thử nghiệm: gửi một câu hỏi rất ngắn cho AI để kiểm tra
 * hàng đợi + kết nối AI hoạt động (nút "Thử qua hàng đợi" ở tab AI).
 */
class PingJob implements AiJobHandler
{
    public function __construct(private Ai $ai) {}

    public function handle(AiJob $job): ?array
    {
        return self::ask($this->ai);
    }

    public static function ask(Ai $ai): array
    {
        return $ai->json('generate', 'ping', [], [
            'type' => 'object',
            'properties' => [
                'reply_vi' => ['type' => 'string'],
                'word' => ['type' => 'string'],
                'meaning_vi' => ['type' => 'string'],
            ],
            'required' => ['reply_vi', 'word', 'meaning_vi'],
        ], 'ping', ['timeout' => 20]);
    }
}
