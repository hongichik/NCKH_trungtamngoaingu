<?php

namespace App\Console\Commands;

use App\Support\DbSync\DatabaseSync;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\File;
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

        $id = bin2hex(random_bytes(16));
        $tmp = storage_path('app/db-sync-tmp/'.$id);

        try {
            $this->line('Đang xuất dữ liệu...');
            $parts = $sync->exportToParts($tables, $tmp, (int) config('dbsync.part_bytes'));
            $total = array_sum(array_map('filesize', $parts));
            $this->line(sprintf('Đã đóng gói: <info>%d phần, %s KB</info> (gzip)', count($parts), number_format($total / 1024, 1)));

            $bar = $this->output->createProgressBar(count($parts));
            $bar->start();
            foreach ($parts as $i => $part) {
                $res = $this->client($token)
                    ->retry(3, 2000, throw: false)
                    ->withBody(file_get_contents($part), 'application/gzip')
                    ->post("{$url}/api/db-sync/chunk?id={$id}&seq=".($i + 1));

                if (! $res->successful() || ! $res->json('ok')) {
                    $bar->clear();
                    $this->fail('Gửi phần '.($i + 1)." thất bại (HTTP {$res->status()}): ".$this->message($res));
                }
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();

            $this->line('Máy chủ đang nhập dữ liệu...');
            $res = $this->client($token)
                ->post("{$url}/api/db-sync/commit?id={$id}&migrate=".($this->option('no-migrate') ? 0 : 1));
        } finally {
            File::deleteDirectory($tmp);
        }

        $json = $res->json() ?? [];

        if (! $res->successful() || ! ($json['ok'] ?? false)) {
            $this->error("Thất bại (HTTP {$res->status()}): ".$this->message($res));
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

    private function client(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->timeout((int) config('dbsync.http_timeout', 300))
            ->acceptJson();
    }

    private function message($res): string
    {
        return $res->json('message') ?? mb_substr(strip_tags($res->body()), 0, 300);
    }
}
