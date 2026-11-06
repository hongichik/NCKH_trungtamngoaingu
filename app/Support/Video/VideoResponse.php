<?php

namespace App\Support\Video;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Phát file theo từng đoạn (HTTP Range → 206 Partial Content): trình phát chỉ tải phần sắp xem, tua không phải chờ tải hết.
 * Không cho trình duyệt / proxy lưu đệm, không gợi ý tải xuống.
 */
class VideoResponse
{
    public const MIME = [
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
        'ts' => 'video/mp2t', 'm4s' => 'video/iso.segment', 'aac' => 'audio/aac', 'vtt' => 'text/vtt',
        'key' => 'application/octet-stream', 'm3u8' => 'application/vnd.apple.mpegurl',
    ];

    public static function file(string $absPath): BinaryFileResponse
    {
        abort_unless(is_file($absPath), 404);
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $res = new BinaryFileResponse($absPath, 200, [
            'Content-Type' => self::MIME[$ext] ?? 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
        ], false, ResponseHeaderBag::DISPOSITION_INLINE, false, false);
        $res->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'video.'.$ext);

        return $res;
    }
}
