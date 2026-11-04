<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Đọc / ghi một số biến trong file .env mà không làm hỏng các dòng khác.
 * Mỗi lần ghi đều lưu bản sao .env cũ vào storage/app/env-backups.
 */
class EnvEditor
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? app()->environmentFilePath();
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isWritable(): bool
    {
        return is_file($this->path) ? is_writable($this->path) : is_writable(dirname($this->path));
    }

    public function get(string $key): ?string
    {
        $content = is_file($this->path) ? File::get($this->path) : '';

        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $content, $m)) {
            return null;
        }

        $value = trim($m[1]);

        if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
            return stripcslashes(substr($value, 1, -1));
        }
        if (strlen($value) >= 2 && $value[0] === "'" && str_ends_with($value, "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public function set(array $values): void
    {
        $content = is_file($this->path) ? File::get($this->path) : '';
        $this->backup($content);

        foreach ($values as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new InvalidArgumentException("Tên biến không hợp lệ: {$key}");
            }

            $line = $key.'='.$this->format($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = preg_replace_callback($pattern, fn () => $line, $content, 1);
            } else {
                $content = rtrim($content, "\n")."\n".$line."\n";
            }
        }

        // Ghi ra file tạm rồi đổi tên để không bao giờ để lại .env ghi dở.
        $tmp = $this->path.'.tmp-'.bin2hex(random_bytes(4));
        File::put($tmp, $content);
        @chmod($tmp, is_file($this->path) ? fileperms($this->path) & 0777 : 0640);
        rename($tmp, $this->path);
    }

    private function format(string|int|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;
        if (str_contains($value, "\n") || str_contains($value, "\r")) {
            throw new InvalidArgumentException('Giá trị không được chứa xuống dòng.');
        }

        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@,+-]+$/', $value)) {
            return $value;
        }

        return '"'.addcslashes($value, '"\\$').'"';
    }

    private function backup(string $content): void
    {
        if ($content === '') {
            return;
        }

        $dir = storage_path('app/env-backups');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/.env-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(2)), $content);

        // Giữ 20 bản gần nhất.
        $files = collect(File::glob($dir.'/.env-*'))->sort()->values();
        $files->slice(0, max(0, $files->count() - 20))->each(fn ($f) => File::delete($f));
    }
}
