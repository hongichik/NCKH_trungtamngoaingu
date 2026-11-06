<?php

namespace App\Support\Ai;

use App\Models\TtsCache;
use App\Support\Exercise\TtsService;
use Illuminate\Support\Facades\Storage;

/**
 * Giọng đọc AI cho mọi nút "Nghe" / loa trên trang (thay giọng có sẵn của trình duyệt).
 * Mỗi đoạn tạo một lần, lưu theo mã băm (nội dung + giọng + ngôn ngữ + model) trong bảng tts_cache
 * → đoạn đã tạo dùng lại mãi cho mọi người, không tốn thêm.
 */
class SpeechCache
{
    /** Độ dài tối đa một đoạn (trình duyệt tự cắt câu dài thành nhiều đoạn). */
    public const MAX_CHARS = 1200;

    public function __construct(private Ai $ai) {}

    public static function voice(string $lang, string $gender): string
    {
        return TtsService::VOICES[$gender === 'male' ? 'male' : 'female'][0];
    }

    /** Đường dẫn file mp3 (đĩa public) của đoạn — tạo bằng AI nếu chưa có. */
    public function path(string $text, string $lang, string $gender = 'female'): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $lang = $lang === 'zh' ? 'zh' : 'en';
        $voice = self::voice($lang, $gender);
        $hash = hash('sha256', implode('|', [$lang, $voice, config('ai.models.tts'), $text]));

        $cached = TtsCache::where('hash', $hash)->first();
        if ($cached && Storage::disk('public')->exists($cached->file_path)) {
            return $cached->file_path;
        }
        $instructions = $lang === 'zh'
            ? 'Speak standard Mandarin Chinese clearly at a slightly slow pace for learners.'
            : 'Speak clearly at a natural, slightly slow pace for English learners. Neutral, friendly tone.';
        // Tính vào hạn mức chung của hệ thống (không trừ lượt AI cá nhân của học viên — để dành cho chấm bài).
        $mp3 = $this->ai->for(null)->speak($text, $voice, $instructions);
        $path = 'tts/'.substr($hash, 0, 2).'/'.$hash.'.mp3';
        Storage::disk('public')->put($path, $mp3);
        TtsCache::updateOrCreate(['hash' => $hash], ['language' => $lang, 'voice' => $voice, 'text' => $text, 'file_path' => $path]);

        return $path;
    }
}
