<?php

namespace App\Support\CustomExercise\Jobs;

use App\Models\AiJob;
use App\Models\CustomExerciseItem;
use App\Support\Ai\Jobs\AiJobHandler;
use App\Support\CustomExercise\ItemAiService;

/** AI cho một phần của bài tập đã lưu: dựng lại nội dung (rebuild) hoặc tạo giọng đọc AI cho file nghe (tts). */
class CustomItemAiJob implements AiJobHandler
{
    public function __construct(private ItemAiService $service) {}

    public function handle(AiJob $job): ?array
    {
        $item = CustomExerciseItem::with('exercise.course')->find($job->payload['item_id'] ?? 0);
        if (! $item || ($item->meta['ai']['status'] ?? null) !== 'pending') {
            return ['skipped' => true];
        }
        match ($job->payload['action'] ?? '') {
            'tts' => $this->service->tts($item, $job->payload),
            default => $this->service->rebuild($item, $job->payload),
        };

        return ['ok' => true];
    }

    public function failed(AiJob $job, \Throwable $e): void
    {
        if ($item = CustomExerciseItem::find($job->payload['item_id'] ?? 0)) {
            $this->service->finish($item, 'AI lỗi: '.mb_substr($e->getMessage(), 0, 250));
        }
    }
}
