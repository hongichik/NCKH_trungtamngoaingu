<?php

namespace App\Support\Exercise;

use App\Models\ExerciseAttempt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Tự xoá ghi âm cũ để không đầy ổ hosting:
 *  - ghi âm của lượt làm đã chấm xong quá N ngày (config ai.recordings_keep_days; 0 = giữ mãi);
 *  - ghi âm tạm (ghi xong nhưng không nộp) quá 2 ngày.
 * Hosting không chạy được schedule:run → ai:work gọi runDaily() (tối đa 1 lần / ngày).
 */
class RecordingPruner
{
    public function runDaily(): ?array
    {
        if (! Cache::add('recordings:pruned:'.now()->toDateString(), 1, 86400 * 2)) {
            return null;
        }

        return $this->prune();
    }

    /** @return array{attempts: int, files: int, tmp: int} */
    public function prune(?int $days = null): array
    {
        $days ??= (int) config('ai.recordings_keep_days', 30);
        $disk = Storage::disk('local');
        $stats = ['attempts' => 0, 'files' => 0, 'tmp' => 0];

        if ($days > 0) {
            ExerciseAttempt::whereNotNull('files')->where('files', '!=', '[]')
                ->where('status', '!=', 'grading')->where('created_at', '<', now()->subDays($days))
                ->chunkById(200, function ($attempts) use ($disk, &$stats) {
                    foreach ($attempts as $a) {
                        $files = $a->files ?? [];
                        $changed = false;
                        foreach ($files as $code => $f) {
                            if (! empty($f['path'])) {
                                $disk->delete($f['path']);
                                $files[$code] = ['path' => null, 'duration' => $f['duration'] ?? null, 'pruned_at' => now()->toDateString()];
                                $stats['files']++;
                                $changed = true;
                            }
                        }
                        if ($changed) {
                            $a->update(['files' => $files]);
                            $disk->deleteDirectory("recordings/attempt-{$a->id}");
                            $stats['attempts']++;
                        }
                    }
                });
        }

        $limit = now()->subDays(2)->getTimestamp();
        foreach ($disk->allFiles('recordings/tmp') as $f) {
            if ($disk->lastModified($f) < $limit) {
                $disk->delete($f);
                $stats['tmp']++;
            }
        }

        return $stats;
    }
}
