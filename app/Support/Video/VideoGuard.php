<?php

namespace App\Support\Video;

use App\Models\LessonVideo;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Chống tải lậu video (nhiều lớp, không chặn được tuyệt đối việc quay màn hình):
 *  - Link phát ký số HMAC, hết hạn sau vài phút, gắn với học viên + phiên đăng nhập → copy link sang máy khác / hết hạn là hỏng.
 *  - File nằm ngoài public, chỉ phát qua controller kiểm tra quyền; kiểm tra Referer.
 *  - Mỗi học viên chỉ xem trên một phiên tại một thời điểm.
 *  - Gói HLS mã hoá AES-128: khoá giải mã chỉ cấp qua link ký số.
 *  - Bunny Stream: link nhúng có token hết hạn (DRM tuỳ gói dịch vụ).
 */
class VideoGuard
{
    public function signature(string $kind, int $videoId, int $studentId, string $sessionId, string $file, int $exp): string
    {
        return hash_hmac('sha256', implode('|', [$kind, $videoId, $studentId, $sessionId, $file, $exp]), (string) config('app.key'));
    }

    /** Tham số query ký số cho một tài nguyên của video. */
    public function params(string $kind, LessonVideo $video, Student $student, string $sessionId, string $file = ''): array
    {
        $exp = now()->getTimestamp() + (int) config('video.link_ttl', 300);

        return array_filter(['f' => $file, 'exp' => $exp, 'sig' => $this->signature($kind, $video->id, $student->id, $sessionId, $file, $exp)], fn ($v) => $v !== '');
    }

    public function verify(Request $request, string $kind, LessonVideo $video, Student $student): bool
    {
        $exp = (int) $request->query('exp');
        $sig = (string) $request->query('sig');
        if ($exp < now()->getTimestamp() || $sig === '') {
            return false;
        }
        $expected = $this->signature($kind, $video->id, $student->id, $request->session()->getId(), (string) $request->query('f', ''), $exp);

        return hash_equals($expected, $sig);
    }

    /** Referer (nếu trình duyệt gửi) phải là chính trang web này. */
    public function refererOk(Request $request): bool
    {
        $ref = $request->headers->get('referer');

        return ! $ref || parse_url($ref, PHP_URL_HOST) === $request->getHost();
    }

    /** Đăng ký phiên đang xem; false nếu học viên đang xem ở phiên khác (trong 60 giây gần nhất). */
    public function claimViewer(Student $student, string $sessionId): bool
    {
        if (! config('video.single_session')) {
            return true;
        }
        $key = 'video-viewer:'.$student->id;
        $cur = Cache::get($key);
        if ($cur && $cur['session'] !== $sessionId && $cur['at'] > now()->getTimestamp() - 60) {
            return false;
        }
        Cache::put($key, ['session' => $sessionId, 'at' => now()->getTimestamp()], 120);

        return true;
    }

    /** Học viên chủ động "xem ở thiết bị này" → chiếm quyền xem. */
    public function takeOver(Student $student, string $sessionId): void
    {
        Cache::put('video-viewer:'.$student->id, ['session' => $sessionId, 'at' => now()->getTimestamp()], 120);
    }

    public function bunnyEnabled(): bool
    {
        return filled(config('video.bunny.library_id')) && filled(config('video.bunny.token_key'));
    }

    /** Link nhúng Bunny Stream có token hết hạn (Token Authentication: sha256(key + guid + expires)). */
    public function bunnyEmbedUrl(LessonVideo $video, ?int $ttl = null): string
    {
        $exp = now()->getTimestamp() + ($ttl ?? (int) config('video.link_ttl', 300));
        $lib = config('video.bunny.library_id');
        $token = hash('sha256', config('video.bunny.token_key').$video->external_id.$exp);

        return "https://iframe.mediadelivery.net/embed/{$lib}/{$video->external_id}?token={$token}&expires={$exp}&autoplay=false&preload=true";
    }

    /** Viết lại playlist HLS: mọi đoạn (.ts/.m4s) và khoá (#EXT-X-KEY URI) thành link ký số. */
    public function rewritePlaylist(string $playlist, callable $sign): string
    {
        $out = [];
        foreach (preg_split('/\r\n|\n|\r/', $playlist) as $line) {
            $t = trim($line);
            if ($t === '') {
                $out[] = $line;
            } elseif (str_starts_with($t, '#EXT-X-KEY') || str_starts_with($t, '#EXT-X-MAP')) {
                $out[] = preg_replace_callback('/URI="([^"]+)"/', fn ($m) => 'URI="'.$sign(basename($m[1])).'"', $t);
            } elseif (str_starts_with($t, '#')) {
                $out[] = $t;
            } else {
                $out[] = $sign(basename($t));
            }
        }

        return implode("\n", $out);
    }
}
