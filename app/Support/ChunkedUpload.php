<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Nhận file lớn theo từng mảnh (hosting giới hạn post_max_size 8 MB).
 * Trình duyệt gửi lần lượt các mảnh ≤ 2 MB (body thô) kèm ?part=i&total=n;
 * khi đủ mảnh thì ghép lại và trả về đường dẫn file hoàn chỉnh.
 * Mảnh đã nhận được ghi đè nếu gửi lại (cho phép thử lại khi rớt mạng).
 */
class ChunkedUpload
{
    public const PART_MAX_BYTES = 3 * 1024 * 1024;

    public static function dir(string $key): string
    {
        if (! preg_match('/^[A-Za-z0-9_-]{6,80}$/', $key)) {
            throw new InvalidArgumentException('Mã phiên tải lên không hợp lệ.');
        }

        return storage_path('app/private/uploads/'.$key);
    }

    /**
     * @return string|null đường dẫn file đã ghép (khi nhận đủ mảnh), null nếu còn thiếu
     */
    public function receive(Request $request, string $key, int $part, int $total, int $maxTotalBytes): ?string
    {
        if ($total < 1 || $total > 500 || $part < 0 || $part >= $total) {
            throw new InvalidArgumentException('Số thứ tự mảnh không hợp lệ.');
        }

        $dir = self::dir($key);
        File::ensureDirectoryExists($dir);

        $path = sprintf('%s/part-%04d', $dir, $part);
        $in = $request->getContent(true);
        $out = fopen($path.'.tmp', 'wb');
        $bytes = stream_copy_to_stream($in, $out, self::PART_MAX_BYTES + 1);
        fclose($out);

        if ($bytes === 0 || $bytes > self::PART_MAX_BYTES) {
            @unlink($path.'.tmp');
            throw new InvalidArgumentException($bytes === 0 ? 'Mảnh dữ liệu rỗng (có thể vượt giới hạn của máy chủ).' : 'Mảnh dữ liệu quá lớn.');
        }
        rename($path.'.tmp', $path);

        $parts = glob($dir.'/part-[0-9][0-9][0-9][0-9]');
        if (count($parts) < $total) {
            return null;
        }

        $size = array_sum(array_map('filesize', $parts));
        if ($size > $maxTotalBytes) {
            File::deleteDirectory($dir);
            throw new InvalidArgumentException('File vượt dung lượng cho phép ('.round($maxTotalBytes / 1048576).' MB).');
        }

        sort($parts);
        $assembled = $dir.'/assembled';
        $out = fopen($assembled, 'wb');
        foreach ($parts as $p) {
            $in = fopen($p, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            unlink($p);
        }
        fclose($out);

        return $assembled;
    }

    public static function cleanup(string $key): void
    {
        File::deleteDirectory(self::dir($key));
    }
}
