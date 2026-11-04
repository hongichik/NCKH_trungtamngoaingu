<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores an uploaded image on the public disk, shrinking it to fit within
 * $maxWidth x $maxHeight and re-encoding it for the web (adapted from the
 * truongthuchanh project's editor upload). Falls back to storing the original
 * file untouched if GD is missing or the image can't be decoded.
 */
class ImageOptimizer
{
    public static function store(UploadedFile $file, string $directory, int $maxWidth = 1600, int $maxHeight = 1600, int $quality = 85): string
    {
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image';
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $path = trim($directory, '/').'/'.now()->format('Ymd-His').'-'.Str::random(6).'-'.Str::limit($name, 60, '').'.'.$extension;

        try {
            $binary = self::optimize($file->getRealPath(), $extension, $maxWidth, $maxHeight, $quality);
        } catch (Throwable) {
            $binary = null;
        }

        if ($binary === null) {
            return $file->storeAs(dirname($path), basename($path), 'public');
        }

        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /**
     * Returns the re-encoded image bytes, or null when the original should be kept as-is.
     */
    protected static function optimize(string $sourcePath, string $extension, int $maxWidth, int $maxHeight, int $quality): ?string
    {
        // Animated GIFs and SVGs would be broken by GD re-encoding.
        if (! function_exists('imagecreatetruecolor') || in_array($extension, ['gif', 'svg'], true)) {
            return null;
        }

        [$width, $height, $type] = getimagesize($sourcePath) ?: [0, 0, 0];
        if (! $width || ! $height) {
            return null;
        }

        $source = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($sourcePath) : false,
            default => false,
        };
        if (! $source) {
            return null;
        }

        // Phone photos store rotation in EXIF rather than in the pixels.
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = @exif_read_data($sourcePath)['Orientation'] ?? 1;
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if ($angle) {
                $source = imagerotate($source, $angle, 0);
                [$width, $height] = [imagesx($source), imagesy($source)];
            }
        }

        $ratio = min(1, $maxWidth / $width, $maxHeight / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        if ($type !== IMAGETYPE_JPEG) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        match ($type) {
            IMAGETYPE_PNG => imagepng($canvas, null, 8),
            IMAGETYPE_WEBP => imagewebp($canvas, null, $quality),
            default => imagejpeg($canvas, null, $quality),
        };
        $binary = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        return $binary ?: null;
    }
}
