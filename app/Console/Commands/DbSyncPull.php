<?php

namespace App\Console\Commands;

use App\Support\DbSync\DatabaseSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class DbSyncPull extends Command
{
    protected $signature = 'db:sync-pull
        {--tables=* : Chỉ kéo các bảng này}
        {--exclude=* : Bỏ qua các bảng này}
        {--url= : Ghi đè DB_SYNC_REMOTE_URL}
        {--force : Không hỏi xác nhận}';

    protected $description = 'Kéo dữ liệu CSDL từ máy chủ về máy này (ghi đè dữ liệu local)';

    public function handle(DatabaseSync $sync): int
    {
        $url = rtrim((string) ($this->option('url') ?: config('dbsync.remote_url')), '/');
        $token = (string) config('dbsync.token');

        if ($url === '' || $token === '') {
            $this->error('Cần cấu hình DB_SYNC_REMOTE_URL và DB_SYNC_TOKEN trong .env');

            return self::FAILURE;
        }

        if (app()->isProduction()) {
            $this->error('Không chạy db:sync-pull trên môi trường production.');

            return self::FAILURE;
        }

        $this->warn("Dữ liệu LOCAL sẽ bị thay thế bằng dữ liệu từ {$url} (có sao lưu trong storage/app/db-sync-backups).");
        if (! $this->option('force') && ! $this->confirm('Tiếp tục?')) {
            return self::SUCCESS;
        }

        $this->line('Đang tải dữ liệu từ máy chủ...');
        $response = Http::withToken($token)
            ->timeout((int) config('dbsync.http_timeout', 300))
            ->get($url.'/api/db-sync/export', array_filter([
                'tables' => $this->option('tables') ?: null,
                'exclude' => $this->option('exclude') ?: null,
            ]));

        if (! $response->successful()) {
            $this->error("Thất bại (HTTP {$response->status()}): ".($response->json('message') ?? substr($response->body(), 0, 500)));

            return self::FAILURE;
        }

        try {
            $payload = $sync->decode($response->body());

            $this->call('migrate', ['--force' => true]);

            if ($missing = $sync->missingMigrations($payload)) {
                $this->error('Máy này thiếu migration so với máy chủ (hãy git pull): '.implode(', ', $missing));

                return self::FAILURE;
            }

            $result = $sync->import($payload);
        } catch (Throwable $e) {
            $this->error('Nhập dữ liệu thất bại, đã hoàn tác: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Bảng', 'Số dòng'], collect($result['tables'])->map(fn ($n, $t) => [$t, $n])->values()->all());
        if ($result['skipped']) {
            $this->warn('Bỏ qua (local không có bảng): '.implode(', ', $result['skipped']));
        }
        $this->info('Xong. Bản sao lưu local: '.$result['backup']);

        return self::SUCCESS;
    }
}
