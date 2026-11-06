<?php

namespace App\Http\Controllers;

use App\Support\Ai\AiException;
use App\Support\Ai\SpeechCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /tts?t=<base64 chữ>&lang=en|zh&g=female|male → file mp3 giọng đọc AI (public/js/speech.js gọi thẳng làm src của <audio>).
 * Chỉ người đã đăng nhập (quản trị, giáo viên, học viên). Lỗi (AI tắt / hết hạn mức) → 503, trình duyệt tự quay về giọng có sẵn.
 */
class TtsController extends Controller
{
    public function __invoke(Request $request, SpeechCache $speech): BinaryFileResponse|JsonResponse
    {
        abort_unless(Auth::guard('web')->check() || Auth::guard('teacher')->check() || Auth::guard('student')->check(), 401);
        if (! config('ai.tts_enabled') || ! filled(config('ai.openai.key'))) {
            return response()->json(['message' => 'Giọng đọc AI đang tắt.'], 503);
        }
        $text = trim((string) base64_decode((string) $request->query('t'), true));
        if ($text === '' || ! mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > SpeechCache::MAX_CHARS) {
            return response()->json(['message' => 'Đoạn đọc không hợp lệ.'], 422);
        }
        try {
            $path = $speech->path($text, (string) $request->query('lang'), (string) $request->query('g'));
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->file(Storage::disk('public')->path($path), ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'private, max-age=31536000, immutable']);
    }
}
