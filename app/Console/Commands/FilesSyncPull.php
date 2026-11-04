<?php

namespace App\Console\Commands;

use App\Support\DbSync\FileSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FilesSyncPull extends Command
{
    protected $signature = 'files:sync-pull
        {--delete : Xoá ở máy này các file không có trên máy chủ}
        {--dry-run : Chỉ liệt kê, không tải}
        {--url= : Ghi đè DB_SYNC_REMOTE_URL}
        {--force : Không hỏi xác nhận}';

    protected $description = 'Tải file (ảnh, file upload...) trong các thư mục đồng bộ từ máy chủ về máy này';

    public function handle(FileSync $files): int
    {
        $url = rtrim((string) ($this->option('url') ?: config('dbsync.remote_url')), '/');
        $token = (string) config('dbsync.token');

        if ($url === '' || $token === '') {
            $this->error('Cần cấu hình DB_SYNC_REMOTE_URL và DB_SYNC_TOKEN trong .env');

            return self::FAILURE;
        }

        if (app()->isProduction()) {
            $this->error('Không chạy files:sync-pull trên môi trường production.');

            return self::FAILURE;
        }

        $dirs = $files->dirs();
        $http = fn () => Http::withToken($token)->timeout((int) config('dbsync.http_timeout', 300));

        $res = $http()->acceptJson()->get("{$url}/api/db-sync/files/manifest", ['dirs' => $dirs]);
        if (! $res->successful() || ! $res->json('ok')) {
            $this->error("Không lấy được danh sách file trên máy chủ (HTTP {$res->status()}): ".($res->json('message') ?? mb_substr($res->body(), 0, 300)));

            return self::FAILURE;
        }

        $remote = array_map(fn ($v) => [(int) $v[0], (int) $v[1]], $res->json('files', []));
        // Chỉ nhận file nằm trong thư mục mà máy này cũng cho phép.
        $remote = array_filter($remote, fn ($p) => $files->resolve($p) !== null, ARRAY_FILTER_USE_KEY);
        $diff = $files->diff($remote, $files->manifest());
        $delete = $this->option('delete') ? $diff['delete'] : [];

        $this->line(sprintf('Cần tải: <info>%d file</info>; cần xoá ở máy này: <info>%d</info>', count($diff['send']), count($delete)));

        if ($this->option('dry-run')) {
            foreach ($diff['send'] as $p) {
                $this->line("  + {$p}");
            }
            foreach ($delete as $p) {
                $this->line("  - {$p}");
            }

            return self::SUCCESS;
        }

        if (! $diff['send'] && ! $delete) {
            $this->info('Không có gì thay đổi.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Tiếp tục?', true)) {
            return self::SUCCESS;
        }

        $failed = 0;
        $bar = $this->output->createProgressBar(count($diff['send']));
        $bar->start();
        foreach ($diff['send'] as $path) {
            $r = $http()->retry(2, 1000, throw: false)->get("{$url}/api/db-sync/files/get", ['path' => $path]);
            if ($r->successful()) {
                $stream = fopen('php://temp', 'r+');
                fwrite($stream, $r->body());
                rewind($stream);
                $files->write($files->resolve($path), $stream, $remote[$path][1]);
                fclose($stream);
            } else {
                $failed++;
                $bar->clear();
                $this->error("  ✗ {$path}: HTTP {$r->status()}");
                $bar->display();
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        foreach ($delete as $path) {
            @unlink($files->resolve($path));
        }

        $this->info(sprintf('Xong: đã tải %d, đã xoá %d, lỗi %d.', count($diff['send']) - $failed, count($delete), $failed));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
