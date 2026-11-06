<?php

namespace App\Support\AiImport\Jobs;

use App\Models\AiImport;
use App\Models\AiJob;
use App\Support\Ai\Ai;
use App\Support\Ai\AiException;
use App\Support\Ai\Jobs\AiJobHandler;
use App\Support\AiImport\AiImportService;
use Illuminate\Support\Facades\Storage;

/** Đọc chữ các trang scan / ảnh chụp của tài liệu (gửi song song). Trang cuối xong → sang lượt chia phần. */
class ImportOcrJob implements AiJobHandler
{
    public function __construct(private Ai $ai, private AiImportService $service) {}

    public function handle(AiJob $job): ?array
    {
        $import = AiImport::find($job->payload['import_id'] ?? 0);
        if (! $import || $import->status !== 'ocr') {
            return ['skipped' => true];
        }
        $wanted = array_map('intval', (array) ($job->payload['pages'] ?? [$job->payload['page'] ?? 0]));
        // Trang chưa đọc (lần thử lại chỉ gửi trang còn thiếu)
        $todo = collect($import->pages ?? [])->filter(fn ($p) => in_array((int) $p['no'], $wanted, true) && empty($p['done']))
            ->map(fn ($p) => Storage::disk('local')->path($p['image']))->all();
        if (! $todo) {
            return ['skipped' => true];
        }
        $texts = $this->ai->for($this->service->actorOf($import))->ocrMany($todo, $import->course->language);

        $import->refresh();
        $pages = $import->pages;
        $error = null;
        $chars = 0;
        foreach ($texts as $i => $text) {
            if ($text instanceof AiException) {
                $error ??= $text;

                continue;
            }
            $pages[$i]['text'] = $text;
            $pages[$i]['done'] = true;
            $chars += mb_strlen($text);
        }
        $import->update(['pages' => $pages]);
        if ($error) {
            throw $error;   // việc nền thử lại — chỉ gửi lại trang lỗi
        }
        if ($import->status === 'ocr' && collect($pages)->where('ocr', true)->whereNull('done')->isEmpty()) {
            $this->service->plan($import->fresh());
        }

        return ['chars' => $chars];
    }

    public function failed(AiJob $job, \Throwable $e): void
    {
        AiImport::whereKey($job->payload['import_id'] ?? 0)->update(['status' => 'failed', 'error' => 'Không đọc được trang tài liệu: '.mb_substr($e->getMessage(), 0, 300)]);
    }
}
