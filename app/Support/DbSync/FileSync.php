<?php

namespace App\Support\DbSync;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Đồng bộ file trong các thư mục được cho phép (mặc định storage/app/public).
 * So sánh theo kích thước + thời gian sửa (mtime); sau khi nhận file, mtime
 * được đặt giống máy gửi nên lần sau không phải gửi lại.
 *
 * Đường dẫn luôn là đường dẫn tương đối tính từ thư mục gốc dự án,
 * ví dụ "storage/app/public/posts/anh.jpg".
 */
class FileSync
{
    /** Phần mở rộng không bao giờ được ghi qua API (tránh upload mã thực thi). */
    public const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps', 'pht', 'pgif',
        'shtml', 'cgi', 'pl', 'py', 'sh', 'bash', 'asp', 'aspx', 'jsp', 'exe', 'htaccess', 'htpasswd', 'ini',
    ];

    /** Thư mục nội bộ chứa dữ liệu nhạy cảm, không bao giờ được đồng bộ. */
    public const PROTECTED_DIRS = [
        'storage/app/db-sync-backups',
        'storage/app/db-sync-sessions',
        'storage/app/db-sync-tmp',
        'storage/app/env-backups',
    ];

    /**
     * Thư mục được cấu hình có hợp lệ không (chỉ trong storage/app/ hoặc public/).
     */
    public static function isAllowedDir(string $dir): bool
    {
        $dir = trim(str_replace('\\', '/', $dir), '/');

        if ($dir === '' || str_contains($dir, "\0") || in_array('..', explode('/', $dir), true) || in_array('.', explode('/', $dir), true)) {
            return false;
        }

        if (! str_starts_with($dir.'/', 'storage/app/') && ! str_starts_with($dir.'/', 'public/')) {
            return false;
        }

        foreach (self::PROTECTED_DIRS as $protected) {
            if ($dir === $protected || str_starts_with($dir.'/', $protected.'/') || str_starts_with($protected.'/', $dir.'/')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function dirs(): array
    {
        return array_values(array_filter(
            array_map(fn ($d) => trim(str_replace('\\', '/', $d), '/'), config('dbsync.file_dirs', [])),
            fn ($d) => self::isAllowedDir($d)
        ));
    }

    /**
     * Danh sách file: [path => [size, mtime]].
     *
     * @param  list<string>|null  $dirs  lọc theo các thư mục này (phải nằm trong dirs())
     * @return array<string, array{0:int, 1:int}>
     */
    public function manifest(?array $dirs = null): array
    {
        $allowed = $this->dirs();
        $dirs = $dirs === null ? $allowed : array_values(array_intersect($dirs, $allowed));
        $files = [];

        foreach ($dirs as $dir) {
            $abs = base_path($dir);
            if (! is_dir($abs)) {
                continue;
            }

            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($it as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isLink() || ! $file->isFile()) {
                    continue;
                }

                $rel = $dir.'/'.str_replace('\\', '/', substr($file->getPathname(), strlen($abs) + 1));
                if ($this->resolve($rel) === null) {
                    continue;
                }

                $files[$rel] = [$file->getSize(), $file->getMTime()];
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Chuyển đường dẫn tương đối thành tuyệt đối nếu hợp lệ, ngược lại trả về null.
     */
    public function resolve(string $rel): ?string
    {
        $rel = str_replace('\\', '/', $rel);

        if ($rel === '' || str_contains($rel, "\0") || str_starts_with($rel, '/')) {
            return null;
        }

        $segments = explode('/', $rel);
        foreach ($segments as $seg) {
            // Không cho "..", ".", đoạn rỗng, hay file/thư mục ẩn (.env, .htaccess, .gitignore...).
            if ($seg === '' || $seg[0] === '.') {
                return null;
            }
        }

        $name = strtolower(end($segments));
        foreach (explode('.', $name) as $i => $part) {
            // Chặn cả dạng "anh.php.jpg" mà một số máy chủ vẫn thực thi.
            if ($i > 0 && in_array($part, self::BLOCKED_EXTENSIONS, true)) {
                return null;
            }
        }

        $inAllowedDir = false;
        foreach ($this->dirs() as $dir) {
            if (str_starts_with($rel, $dir.'/')) {
                $inAllowedDir = true;
                break;
            }
        }
        if (! $inAllowedDir) {
            return null;
        }

        return base_path($rel);
    }

    /**
     * So sánh 2 manifest.
     *
     * @return array{send: list<string>, delete: list<string>}
     *                                                         send: file có ở nguồn mà đích thiếu/khác; delete: file chỉ có ở đích
     */
    public function diff(array $source, array $target): array
    {
        $send = [];
        foreach ($source as $path => [$size, $mtime]) {
            if (! isset($target[$path]) || $target[$path][0] !== $size || $target[$path][1] !== $mtime) {
                $send[] = $path;
            }
        }

        return ['send' => $send, 'delete' => array_values(array_diff(array_keys($target), array_keys($source)))];
    }

    /**
     * Ghi file từ một stream, đặt mtime theo máy gửi.
     *
     * @param  resource  $stream
     */
    public function write(string $abs, $stream, ?int $mtime = null): int
    {
        if (! is_dir(dirname($abs))) {
            mkdir(dirname($abs), 0755, true);
        }

        $tmp = $abs.'.part-'.bin2hex(random_bytes(4));
        $out = fopen($tmp, 'wb');
        $bytes = stream_copy_to_stream($stream, $out);
        fclose($out);
        rename($tmp, $abs);

        if ($mtime) {
            touch($abs, $mtime);
        }

        return (int) $bytes;
    }
}
