<?php

namespace App\Support\AiImport\Jobs;

use App\Models\AiImport;
use App\Models\AiJob;
use App\Support\Ai\Jobs\AiJobHandler;
use App\Support\AiImport\AiImportService;

/** Lượt 2: AI dựng nội dung các phần theo lược đồ của dạng bài đã chọn — nhiều phần gửi song song trong một việc. */
class ImportBuildJob implements AiJobHandler
{
    public function __construct(private AiImportService $service) {}

    /** @return list<int> */
    private static function indexes(AiJob $job): array
    {
        return array_map('intval', (array) ($job->payload['sections'] ?? [$job->payload['section'] ?? 0]));
    }

    public function handle(AiJob $job): ?array
    {
        $import = AiImport::find($job->payload['import_id'] ?? 0);
        if (! $import || ! in_array($import->status, ['building', 'review'], true)) {
            return ['skipped' => true];
        }
        $this->service->runBuildMany($import, self::indexes($job), (int) ($job->payload['round'] ?? 1));

        return ['sections' => self::indexes($job)];
    }

    public function failed(AiJob $job, \Throwable $e): void
    {
        $import = AiImport::find($job->payload['import_id'] ?? 0);
        if (! $import) {
            return;
        }
        $sections = $import->sections;
        foreach (self::indexes($job) as $i) {
            if (isset($sections[$i]) && $sections[$i]['status'] !== 'ok') {
                $sections[$i]['status'] = 'invalid';
                $sections[$i]['errors'] = ['AI lỗi khi dựng phần này: '.mb_substr($e->getMessage(), 0, 200)];
            }
        }
        $import->update(['sections' => $sections]);
        app(AiImportService::class)->maybeReview($import);
    }
}
