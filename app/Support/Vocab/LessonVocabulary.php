<?php

namespace App\Support\Vocab;

use App\Models\CourseLesson;
use App\Support\Ai\Ai;

/**
 * Danh sách từ vựng của bài học (cho học viên ôn thẻ). Giáo viên sửa dạng mỗi dòng một từ:
 *   tiếng Trung:  汉字 | pinyin | nghĩa tiếng Việt | ví dụ
 *   tiếng Anh:    word | từ loại | nghĩa tiếng Việt | ví dụ
 * AI lập danh sách từ tên, tóm tắt, ghi chú bài học và các bài tập nối từ của bài.
 */
class LessonVocabulary
{
    public const MAX_WORDS = 60;

    public function __construct(private Ai $ai) {}

    public static function zh(CourseLesson $lesson): bool
    {
        return $lesson->course?->language === 'zh';
    }

    /** Danh sách → chữ để sửa trong ô nhập. */
    public static function toText(CourseLesson $lesson): string
    {
        $zh = self::zh($lesson);

        return collect($lesson->vocabulary ?? [])->map(fn ($v) => rtrim(implode(' | ', [
            $v['word'] ?? '', $zh ? ($v['pinyin'] ?? '') : ($v['pos'] ?? ''), $v['meaning_vi'] ?? '', $v['example'] ?? '',
        ]), ' |'))->implode("\n");
    }

    /** Chữ giáo viên nhập → danh sách (bỏ dòng trống, bỏ trùng). */
    public static function parse(string $text, bool $zh): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            $cols = array_map('trim', explode('|', str_replace(['｜', "\t"], '|', $line)));
            $word = $cols[0] ?? '';
            if ($word === '' || mb_strlen($word) > 120) {
                continue;
            }
            $key = mb_strtolower($word);
            $out[$key] ??= array_filter([
                'word' => $word,
                'pinyin' => $zh ? ($cols[1] ?? '') : '',
                'pos' => $zh ? '' : ($cols[1] ?? ''),
                'meaning_vi' => $cols[2] ?? '',
                'example' => $cols[3] ?? '',
            ], fn ($v) => $v !== '') + ['word' => $word];
        }

        return array_slice(array_values($out), 0, self::MAX_WORDS);
    }

    /** Cặp từ ↔ nghĩa trong các bài tập nối từ của bài (khi chưa có danh sách / gợi ý cho AI). */
    public static function fromExercises(CourseLesson $lesson): array
    {
        $out = [];
        foreach ($lesson->exercises()->with('items')->get() as $ex) {
            foreach ($ex->items->where('type_code', 'general.match_pairs') as $item) {
                $c = $item->content ?? [];
                foreach ($c['items'] ?? [] as $it) {
                    $letter = $c['answers'][$it['id']] ?? null;
                    $idx = $letter !== null ? ord(strtoupper($letter)) - 65 : -1;
                    if (! empty($it['text']) && isset($c['options'][$idx])) {
                        $out[] = ['word' => $it['text'], 'meaning_vi' => $c['options'][$idx]];
                    }
                }
            }
        }

        return $out;
    }

    /** AI lập danh sách từ vựng của bài. @return array danh sách (chưa lưu) */
    public function generate(CourseLesson $lesson, ?object $actor = null): array
    {
        $zh = self::zh($lesson);
        $pairs = collect(self::fromExercises($lesson))->map(fn ($p) => $p['word'].' = '.$p['meaning_vi'])->implode('; ');
        $prompt = 'Lập DANH SÁCH TỪ VỰNG để học viên Việt Nam ôn bằng thẻ ghi nhớ cho bài học '.($zh ? 'tiếng Trung' : 'tiếng Anh')." dưới đây.\n"
            .'Chỉ lấy từ / cụm từ trọng tâm của bài (thường 10–30 mục, tối đa '.self::MAX_WORDS.'), đúng trình độ khoá học "'.($lesson->course?->title).'"'.($lesson->course?->level ? ' ('.$lesson->course->level.')' : '').'. '
            .'Mỗi mục: word = từ gốc'.($zh ? ' bằng chữ Hán giản thể; pinyin = phiên âm có dấu thanh' : '; pos = từ loại viết tắt (n., v., adj., adv., phr.…); pinyin = chuỗi rỗng')
            .'; meaning_vi = nghĩa tiếng Việt ngắn gọn; example = một câu ví dụ ngắn, tự nhiên'.($zh ? ' (chữ Hán, kèm pinyin trong ngoặc)' : '').'. '
            ."Ưu tiên các từ có trong phần TỪ VỰNG của ghi chú; không bịa từ ngoài chủ đề.\n\n"
            ."BÀI HỌC: {$lesson->title}\n".($lesson->summary ? "TÓM TẮT: {$lesson->summary}\n" : '')
            .($lesson->content ? "GHI CHÚ BÀI HỌC:\n".mb_substr($lesson->content, 0, 12000)."\n" : '')
            .($pairs ? "CẶP TỪ TRONG BÀI TẬP NỐI TỪ: {$pairs}\n" : '');
        $schema = ['type' => 'object', 'properties' => ['words' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'word' => ['type' => 'string'], 'pinyin' => ['type' => 'string'], 'pos' => ['type' => 'string'], 'meaning_vi' => ['type' => 'string'], 'example' => ['type' => 'string'],
        ], 'required' => ['word', 'pinyin', 'pos', 'meaning_vi', 'example']]]], 'required' => ['words']];
        $res = $this->ai->for($actor)->jsonRaw('analysis', $prompt, $schema, 'lesson_vocabulary', ['timeout' => 60]);
        $text = collect($res['words'] ?? [])->map(fn ($w) => implode(' | ', [$w['word'], $zh ? $w['pinyin'] : $w['pos'], $w['meaning_vi'], $w['example']]))->implode("\n");

        return self::parse($text, $zh);
    }
}
