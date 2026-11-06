<?php

namespace App\Support\AiImport\Jobs;

use App\Models\AiImport;
use App\Models\AiJob;
use App\Support\Ai\Jobs\AiJobHandler;
use App\Support\AiImport\AiImportService;

/** Lượt 1: AI chia tài liệu thành các phần + chọn dạng bài. */
class ImportPlanJob implements AiJobHandler
{
    public function __construct(private AiImportService $service) {}

    public function handle(AiJob $job): ?array
    {
        $import = AiImport::find($job->payload['import_id'] ?? 0);
        if (! $import || $import->status !== 'planning') {
            return ['skipped' => true];
        }
        $this->service->runPlan($import);

        return ['sections' => count($import->fresh()->sections ?? [])];
    }

    public function failed(AiJob $job, \Throwable $e): void
    {
        AiImport::whereKey($job->payload['import_id'] ?? 0)->update(['status' => 'failed', 'error' => 'AI chưa chia được phần: '.mb_substr($e->getMessage(), 0, 300)]);
    }
}
