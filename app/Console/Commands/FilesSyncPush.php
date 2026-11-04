<?php

namespace App\Console\Commands;

use App\Support\DbSync\FileSync;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class FilesSyncPush extends Command
{
    protected $signature = 'files:sync-push
        {--delete : Xoá trên máy chủ các file không còn ở máy này}
        {--dry-run : Chỉ liệt kê, không gửi}
        {--url= : Ghi đè DB_SYNC_REMOTE_URL}
        {--force : Không hỏi xác nhận}';

    protected $description = 'Đẩy file (ảnh, file upload...) trong các thư mục đồng bộ lên máy chủ';

    public function handle(FileSync $files): int
    {
        $url = rtrim((string) ($this->option('url') ?: config('dbsync.remote_url')), '/');
        $token = (string) config('dbsync.token');

        if ($url === '' || $token === '') {
            $this->error('Cần cấu hình DB_SYNC_REMOTE_URL và DB_SYNC_TOKEN trong .env');

            return self::FAILURE;
        }

        $dirs = $files->dirs();
        if (! $dirs) {
            $this->error('Chưa cấu hình thư mục đồng bộ hợp lệ (DB_SYNC_FILE_DIRS).');

            return self::FAILURE;
        }

        $this->line('Thư mục: <info>'.implode(', ', $dirs).'</info>');
        $local = $files->manifest();

        $res = $this->client($token)->get("{$url}/api/db-sync/files/manifest", ['dirs' => $dirs]);
        if (! $res->successful() || ! $res->json('ok')) {
            $this->error("Không lấy được danh sách file trên máy chủ (HTTP {$res->status()}): ".($res->json('message') ?? mb_substr($res->body(), 0, 300)));

            return self::FAILURE;
        }

        $remoteDirs = $res->json('dirs', []);
        if ($missing = array_diff($dirs, $remoteDirs)) {
            $this->warn('Máy chủ không cho phép các thư mục: '.implode(', ', $missing).' (bỏ qua)');
            $local = array_filter($local, fn ($p) => collect($remoteDirs)->contains(fn ($d) => str_starts_with($p, $d.'/')), ARRAY_FILTER_USE_KEY);
        }

        $remote = array_map(fn ($v) => [(int) $v[0], (int) $v[1]], $res->json('files', []));
        $diff = $files->diff($local, $remote);
        $delete = $this->option('delete') ? $diff['delete'] : [];
        $bytes = array_sum(array_map(fn ($p) => $local[$p][0], $diff['send']));

        $this->line(sprintf('Cần gửi: <info>%d file (%s MB)</info>; cần xoá trên máy chủ: <info>%d</info>; giống nhau: %d',
            count($diff['send']), number_format($bytes / 1048576, 2), count($delete), count($local) - count($diff['send'])));

        if (! $this->option('delete') && $diff['delete']) {
            $this->line("<comment>{$this->plural(count($diff['delete']))} chỉ có trên máy chủ (dùng --delete để xoá).</comment>");
        }

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

        $failed = [];
        $bar = $this->output->createProgressBar(count($diff['send']));
        $bar->start();
        foreach ($diff['send'] as $path) {
            $r = $this->client($token)
                ->retry(2, 1000, throw: false)
                ->withBody(file_get_contents(base_path($path)), 'application/octet-stream')
                ->post("{$url}/api/db-sync/files/put?".http_build_query(['path' => $path, 'mtime' => $local[$path][1]]));

            if (! $r->successful() || ! $r->json('ok')) {
                $failed[$path] = "HTTP {$r->status()} ".($r->json('message') ?? '');
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $deleted = 0;
        if ($delete) {
            foreach (array_chunk($delete, 200) as $batch) {
                $r = $this->client($token)->post("{$url}/api/db-sync/files/delete", ['paths' => $batch]);
                $deleted += count($r->json('deleted', []));
            }
        }

        $this->client($token)->post("{$url}/api/db-sync/files/report", [
            'source' => config('app.url'),
            'uploaded' => count($diff['send']) - count($failed),
            'deleted' => $deleted,
            'failed' => count($failed),
        ]);

        foreach ($failed as $p => $msg) {
            $this->error("  ✗ {$p}: {$msg}");
        }

        $this->info(sprintf('Xong: đã gửi %d, đã xoá %d, lỗi %d.', count($diff['send']) - count($failed), $deleted, count($failed)));
        if ($failed) {
            $this->line('<comment>File lớn hơn post_max_size của máy chủ sẽ bị từ chối — hãy chép thủ công qua FTP.</comment>');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function client(string $token): PendingRequest
    {
        return Http::withToken($token)->timeout((int) config('dbsync.http_timeout', 300))->acceptJson();
    }

    private function plural(int $n): string
    {
        return "{$n} file";
    }
}
