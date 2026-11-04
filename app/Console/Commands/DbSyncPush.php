<?php

namespace App\Console\Commands;

use App\Support\DbSync\DatabaseSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class DbSyncPush extends Command
{
    protected $signature = 'db:sync-push
        {--tables=* : Chỉ đẩy các bảng này (vd: --tables=posts --tables=pages)}
        {--exclude=* : Bỏ qua các bảng này}
        {--no-migrate : Không chạy migrate trên máy chủ trước khi nhập}
        {--url= : Ghi đè DB_SYNC_REMOTE_URL}
        {--force : Không hỏi xác nhận}';

    protected $description = 'Đẩy dữ liệu CSDL ở máy này lên máy chủ (ghi đè dữ liệu các bảng tương ứng trên máy chủ)';

    public function handle(DatabaseSync $sync): int
    {
        $url = rtrim((string) ($this->option('url') ?: config('dbsync.remote_url')), '/');
        $token = (string) config('dbsync.token');

        if ($url === '' || $token === '') {
            $this->error('Cần cấu hình DB_SYNC_REMOTE_URL và DB_SYNC_TOKEN trong .env');

            return self::FAILURE;
        }

        try {
            $tables = $sync->resolveTables($this->option('tables'), $this->option('exclude'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("Máy chủ đích: <info>{$url}</info>");
        $this->line('Số bảng sẽ đẩy: <info>'.count($tables).'</info> ('.implode(', ', $tables).')');
        $this->warn('Dữ liệu các bảng này trên MÁY CHỦ sẽ bị thay thế hoàn toàn (máy chủ tự sao lưu trước khi ghi).');

        if (! $this->option('force') && ! $this->confirm('Tiếp tục?')) {
            return self::SUCCESS;
        }

        $this->line('Đang xuất dữ liệu...');
        $body = $sync->export($tables);
        $this->line('Kích thước payload (gzip): <info>'.number_format(strlen($body) / 1024, 1).' KB</info>');

        $this->line('Đang gửi lên máy chủ...');
        $response = Http::withToken($token)
            ->timeout((int) config('dbsync.http_timeout', 300))
            ->acceptJson()
            ->withBody($body, 'application/gzip')
            ->post($url.'/api/db-sync/import?migrate='.($this->option('no-migrate') ? 0 : 1));

        $json = $response->json() ?? [];

        if (! $response->successful() || ! ($json['ok'] ?? false)) {
            $this->error("Thất bại (HTTP {$response->status()}): ".($json['message'] ?? substr($response->body(), 0, 500)));
            foreach ($json['missing_migrations'] ?? [] as $m) {
                $this->line("  - thiếu migration: {$m}");
            }

            return self::FAILURE;
        }

        if (! empty($json['migrate_output'])) {
            $this->line('<comment>Migrate trên máy chủ:</comment> '.$json['migrate_output']);
        }

        $this->table(['Bảng', 'Số dòng'], collect($json['tables'])->map(fn ($n, $t) => [$t, $n])->values()->all());

        if (! empty($json['skipped'])) {
            $this->warn('Bỏ qua (máy chủ không có bảng): '.implode(', ', $json['skipped']));
        }

        $this->info('Đồng bộ thành công. Bản sao lưu trên máy chủ: '.($json['backup'] ?? '-'));

        return self::SUCCESS;
    }
}
