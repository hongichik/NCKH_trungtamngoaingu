<?php

namespace App\Console\Commands;

use App\Support\Exercise\RecordingPruner;
use Illuminate\Console\Command;

class PruneRecordings extends Command
{
    protected $signature = 'recordings:prune {--days= : Giữ ghi âm bao nhiêu ngày (mặc định theo cấu hình AI)}';

    protected $description = 'Xoá ghi âm bài Nói cũ và ghi âm tạm không nộp';

    public function handle(RecordingPruner $pruner): int
    {
        $s = $pruner->prune($this->option('days') !== null ? (int) $this->option('days') : null);
        $this->info("Đã xoá {$s['files']} ghi âm của {$s['attempts']} lượt làm, {$s['tmp']} ghi âm tạm.");

        return self::SUCCESS;
    }
}
