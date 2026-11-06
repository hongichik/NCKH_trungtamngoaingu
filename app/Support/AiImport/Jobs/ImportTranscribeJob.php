<?php

namespace App\Support\AiImport\Jobs;

use App\Models\AiImport;
use App\Models\AiJob;
use App\Support\Ai\Jobs\AiJobHandler;
use App\Support\AiImport\AiImportService;

/**
 * Chép lời một file nghe đã tải lên (speech-to-text) để AI soạn / dựng đề khớp với nội dung bài nghe.
 * Lúc bắt đầu dựng (trạng thái "transcribing"): file cuối cùng xong → sang bước đọc trang scan / chia phần.
 */
class ImportTranscribeJob implements AiJobHandler
{
    public function __construct(private AiImportService $service) {}

    public function handle(AiJob $job): ?array
    {
        $import = AiImport::find($job->payload['import_id'] ?? 0);
        if (! $import) {
            return ['skipped' => true];
        }
        $chars = $this->service->transcribeAudio($import, (string) ($job->payload['audio_id'] ?? ''));
        $this->service->continueAfterTranscribe($import->fresh());

        return ['chars' => $chars];
    }

    public function failed(AiJob $job, \Throwable $e): void
    {
        $import = AiImport::find($job->payload['import_id'] ?? 0);
        if (! $import) {
            return;
        }
        // Chép lời lỗi không làm hỏng cả lượt dựng: đánh dấu file đó rồi đi tiếp.
        $this->service->markTranscript($import, (string) ($job->payload['audio_id'] ?? ''), 'failed', null, mb_substr($e->getMessage(), 0, 200));
        $this->service->continueAfterTranscribe($import->fresh());
    }
}
