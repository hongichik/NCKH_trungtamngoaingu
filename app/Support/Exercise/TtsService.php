<?php

namespace App\Support\Exercise;

use App\Models\ExerciseSet;
use App\Models\Teacher;
use App\Models\TtsCache;
use App\Support\Ai\Ai;
use Illuminate\Support\Facades\Storage;

/**
 * Giọng đọc AI cho bài Nghe: mỗi câu thoại một file mp3, lưu theo mã băm (nội dung + giọng + ngôn ngữ + model)
 * → câu đã tạo được dùng lại ở MỌI đề, không tốn thêm. File nằm ở storage/app/public/tts (phát qua /storage).
 */
class TtsService
{
    public const VOICES = ['female' => ['coral', 'nova', 'shimmer', 'sage'], 'male' => ['onyx', 'echo', 'ash', 'verse']];

    /**
     * @return array{created: int, reused: int}
     */
    public function synthesize(ExerciseSet $set): array
    {
        $set->loadMissing('unit.textbook');
        $lang = $set->unit->textbook->language;
        $data = $set->data ?? [];
        $voiceOf = $this->voiceMap($data['speakers'] ?? []);
        $ai = app(Ai::class)->for($set->teacher_id ? Teacher::find($set->teacher_id) : null);
        $instructions = $lang === 'zh'
            ? 'Speak standard Mandarin Chinese clearly at a slightly slow pace for learners.'
            : 'Speak clearly at a natural, slightly slow pace for English learners. Neutral, friendly tone.';

        $stats = ['created' => 0, 'reused' => 0];
        $audio = [];
        foreach ($data['script'] ?? [] as $line) {
            $voice = $voiceOf[$line['speaker']] ?? self::VOICES['female'][0];
            $hash = hash('sha256', implode('|', [$lang, $voice, config('ai.models.tts'), $line['text']]));

            $cached = TtsCache::where('hash', $hash)->first();
            if ($cached && Storage::disk('public')->exists($cached->file_path)) {
                $stats['reused']++;
            } else {
                $mp3 = $ai->speak($line['text'], $voice, $instructions);
                $path = 'tts/'.substr($hash, 0, 2).'/'.$hash.'.mp3';
                Storage::disk('public')->put($path, $mp3);
                $cached = TtsCache::updateOrCreate(['hash' => $hash], ['language' => $lang, 'voice' => $voice, 'text' => $line['text'], 'file_path' => $path]);
                $stats['created']++;
            }
            $audio[] = $cached->file_path;
        }

        $set->update(['data' => array_merge($data, ['tts' => $audio])]);

        return $stats;
    }

    /** Mỗi người nói một giọng khác nhau. */
    public function voiceMap(array $speakers): array
    {
        $used = ['female' => 0, 'male' => 0];
        $map = [];
        foreach ($speakers as $s) {
            $g = ($s['voice'] ?? 'female') === 'male' ? 'male' : 'female';
            $map[$s['name']] = self::VOICES[$g][$used[$g]++ % count(self::VOICES[$g])];
        }

        return $map;
    }
}
