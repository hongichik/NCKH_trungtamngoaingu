<?php

namespace App\Support\AiImport\Jobs;

use App\Models\AiImport;
use App\Models\AiJob;
use App\Support\Ai\Jobs\AiJobHandler;
use App\Support\AiImport\AiImportService;

/** Tạo giọng đọc AI cho phần thiếu file nghe (giáo viên đã đồng ý). */
class ImportTtsJob implements AiJobHandler
{
    public function __construct(private AiImportService $service) {}

    public function handle(AiJob $job): ?array
    {
        $import = AiImport::find($job->payload['import_id'] ?? 0);
        if (! $import) {
            return ['skipped' => true];
        }
        $this->service->tts($import, (int) $job->payload['section']);

        return ['ok' => true];
    }
}
