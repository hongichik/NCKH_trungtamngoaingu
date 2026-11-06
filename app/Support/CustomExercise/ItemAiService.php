<?php

namespace App\Support\CustomExercise;

use App\Models\AiImport;
use App\Models\AiJob;
use App\Models\CustomExerciseItem;
use App\Models\Teacher;
use App\Models\User;
use App\Support\Ai\Ai;
use App\Support\Ai\AiException;
use App\Support\Ai\SpeechCache;
use App\Support\AiImport\AiImportService;
use App\Support\QuestionTypes\Registry;
use Illuminate\Support\Facades\Storage;

/**
 * AI cho từng phần của bài tập ĐÃ LƯU (sau màn duyệt):
 *  - rebuild: dựng lại nội dung phần (theo đoạn tài liệu gốc / lấy làm mẫu / theo yêu cầu thêm của giáo viên),
 *    dùng lời chép từ file nghe của phần để câu hỏi khớp bài nghe;
 *  - tts: tạo file nghe bằng giọng đọc AI từ lời bài nghe (giữ file cũ để đổi lại được).
 * Chạy trong việc nền "custom_item_ai"; trạng thái nằm ở meta.ai = {status: pending|error, action, error}.
 */
class ItemAiService
{
    public function __construct(private Ai $ai, private SpeechCache $speech) {}

    public function queue(CustomExerciseItem $item, string $action, array $opts, object $actor): void
    {
        $meta = $item->meta ?? [];
        $meta['ai'] = ['status' => 'pending', 'action' => $action, 'error' => null];
        $item->update(['meta' => $meta]);
        AiJob::dispatch('custom_item_ai', ['item_id' => $item->id, 'action' => $action] + $opts
            + ['actor_type' => $actor instanceof Teacher ? 'teacher' : 'user', 'actor_id' => $actor->id], 'citem:'.$item->id, 0, self::lane($item));
    }

    public static function lane(CustomExerciseItem $item): string
    {
        return 'citem:'.$item->custom_exercise_id;
    }

    public function finish(CustomExerciseItem $item, ?string $error = null, array $patch = []): void
    {
        $item->refresh();
        $meta = $item->meta ?? [];
        if ($error) {
            $meta['ai'] = ['status' => 'error', 'action' => $meta['ai']['action'] ?? null, 'error' => mb_substr($error, 0, 300)];
        } else {
            unset($meta['ai']);
        }
        $item->update(['meta' => array_merge($meta, $patch['meta'] ?? [])] + array_diff_key($patch, ['meta' => 1]));
    }

    private function actor(array $payload): ?object
    {
        return ($payload['actor_type'] ?? '') === 'teacher' ? Teacher::find($payload['actor_id'] ?? 0) : User::find($payload['actor_id'] ?? 0);
    }

    // ---------------------------------------------------------------- lời file nghe

    /** Lời chép từ file nghe của phần (chép một lần, lưu ở meta.transcript kèm đường dẫn file đã chép). */
    public function heard(CustomExerciseItem $item, ?object $actor): ?string
    {
        if (! $item->audio_path || ! empty($item->meta['audio_ai'])) {
            return null;   // không có file, hoặc file là giọng AI (đã có lời)
        }
        if (($item->meta['transcript_of'] ?? null) === $item->audio_path) {
            return $item->meta['transcript'] ?? null;
        }
        $path = Storage::disk('local')->path($item->audio_path);
        if (! is_file($path) || filesize($path) === 0 || filesize($path) > AiImportService::TRANSCRIBE_MAX_BYTES) {
            return null;
        }
        $zh = $item->exercise->course->language === 'zh';
        try {
            $text = trim((string) ($this->ai->for($actor)->transcribe($path, [
                'prompt' => $zh ? '这是汉语听力练习的录音。' : 'This is an English listening exercise recording.',
            ])['text'] ?? ''));
        } catch (AiException) {
            return null;   // chép lời lỗi không chặn việc dựng lại
        }
        $meta = $item->meta ?? [];
        $meta['transcript'] = mb_substr($text, 0, 20000);
        $meta['transcript_of'] = $item->audio_path;
        $item->update(['meta' => $meta]);

        return $text ?: null;
    }

    // ---------------------------------------------------------------- dựng lại

    public function rebuild(CustomExerciseItem $item, array $payload): void
    {
        $actor = $this->actor($payload);
        $type = Registry::get($item->type_code);
        $engine = $type->engine();
        $style = ($payload['style'] ?? 'strict') === 'model' ? 'model' : 'strict';
        $note = trim((string) ($payload['note'] ?? ''));
        $source = trim((string) ($item->meta['source'] ?? ''));
        $import = ! empty($item->meta['ai_import_id']) ? AiImport::find($item->meta['ai_import_id']) : null;
        $heard = $this->heard($item, $actor);
        $current = json_encode($item->content, JSON_UNESCAPED_UNICODE);

        $prompt = ($style === 'model' ? AiImportService::MODEL_MODE_NOTE : '')
            ."Dựng lại NỘI DUNG cho một phần bài tập dạng \"{$type->name}\" (mã {$type->code}), ngôn ngữ học: ".($item->exercise->course->language === 'zh' ? 'tiếng Trung' : 'tiếng Anh').".\n"
            ."Cách làm bài: {$engine->interaction()}\nCách chấm: {$engine->rubric($type)}\n"
            .'LƯỢC ĐỒ JSON của nội dung (bắt buộc đúng):'."\n".json_encode($engine->schema($type), JSON_UNESCAPED_UNICODE)."\nVÍ DỤ hợp lệ:\n".json_encode($type->sample, JSON_UNESCAPED_UNICODE)."\n\n"
            .'Quy tắc: ô trống viết [[1]], [[2]]…; lựa chọn KHÔNG ghi kèm chữ cái / số thứ tự ở đầu; đáp án phải chính xác; '
            .($style === 'strict' && $source !== '' ? 'giữ NGUYÊN VĂN đề, câu hỏi, lựa chọn trong đoạn tài liệu gốc; nếu tài liệu có mục đáp án thì PHẢI dùng đúng đáp án đó. ' : '')
            ."Những chỗ chưa chắc ghi vào flags bằng tiếng Việt. Không có ảnh thì bỏ trường image.\n"
            .($note !== '' ? "\nYÊU CẦU CỦA GIÁO VIÊN (ưu tiên làm theo): {$note}\n" : '')
            .($heard ? "\nLỜI FILE NGHE CỦA PHẦN NÀY (máy tự chép từ file học viên sẽ nghe, có thể sai chính tả nhỏ):\n\"\"\"\n".mb_substr($heard, 0, 12000)."\n\"\"\"\n"
                ."Đề, câu hỏi và đáp án PHẢI khớp nội dung bài nghe này. Tài liệu không in lời thì đưa lời này (đã sửa chính tả) vào stimulus.transcript.\n" : '')
            .($source !== '' ? "\nĐOẠN TÀI LIỆU GỐC CỦA PHẦN \"".($item->title ?: $type->name)."\":\n\"\"\"\n".mb_substr($source, 0, 15000)."\n\"\"\"\n" : '')
            .($import && $style === 'strict' ? "\nTÀI LIỆU ĐẦY ĐỦ (chỉ để tra đáp án và ngữ liệu chung, KHÔNG dựng thêm bài khác):\n\"\"\"\n".app(AiImportService::class)->referenceText($import)."\n\"\"\"\n" : '')
            ."\nNỘI DUNG HIỆN TẠI CỦA PHẦN (JSON — sửa / viết lại từ đây".($source === '' ? ', đây là nguồn chính' : '')."):\n{$current}\n"
            ."\nTrả về content_json = chuỗi JSON của nội dung mới.";
        $images = $item->image_path && Storage::disk('local')->exists($item->image_path) ? [Storage::disk('local')->path($item->image_path)] : [];
        $schema = ['type' => 'object', 'properties' => ['content_json' => ['type' => 'string'], 'flags' => ['type' => 'array', 'items' => ['type' => 'string']]], 'required' => ['content_json', 'flags']];

        $errors = [];
        for ($round = 1; $round <= 2; $round++) {
            $res = $this->ai->for($actor)->jsonRaw('generate', $prompt.($errors ? "\n\nLẦN TRƯỚC CHƯA ĐẠT, hãy sửa: ".implode('; ', array_slice($errors, 0, 8)) : ''), $schema, 'exercise_section', ['timeout' => 90, 'images' => $images]);
            $content = json_decode($res['content_json'], true);
            $errors = is_array($content) ? $engine->validate($content, $type) : ['AI trả về JSON không đọc được.'];
            if (! $errors) {
                $flags = array_values(array_unique(array_merge($res['flags'] ?? [],
                    $style === 'model' ? ['AI tự dựng lại theo mẫu (không chép nguyên văn) — kiểm tra lại câu hỏi và đáp án.'] : [])));
                $this->finish($item, null, ['content' => $content, 'meta' => ['flags' => $flags, 'build_style' => $style]]);

                return;
            }
        }
        $this->finish($item, 'AI dựng chưa đúng lược đồ: '.implode('; ', array_slice($errors, 0, 3)).'. Nội dung cũ được giữ nguyên.');
    }

    // ---------------------------------------------------------------- giọng đọc AI

    /** Lời để đọc: lời bài nghe của phần; bài chép chính tả không có lời chung thì đọc lần lượt từng câu. */
    public static function speechText(CustomExerciseItem $item): string
    {
        $c = $item->content ?? [];
        $t = trim((string) ($c['stimulus']['transcript'] ?? ''));
        if ($t === '' && $item->type()?->engine === 'dictation') {
            $t = collect($c['questions'] ?? [])->pluck('text')->filter()->values()->map(fn ($x, $i) => ($i + 1).'. '.$x)->implode("\n");
        }
        if ($t === '') {
            $t = trim((string) ($item->meta['transcript'] ?? ''));
        }

        return $t;
    }

    public function tts(CustomExerciseItem $item, array $payload): void
    {
        $text = self::speechText($item);
        if ($text === '') {
            $this->finish($item, 'Phần này không có lời bài nghe để đọc (thêm "Lời bài nghe" trong mục Sửa nội dung).');

            return;
        }
        $lang = $item->exercise->course->language;
        $mp3 = '';
        // Cắt theo câu (≤ ~1000 ký tự / đoạn), mỗi đoạn lưu đệm riêng rồi ghép thành 1 file.
        $buf = '';
        $parts = [];
        foreach (preg_split('/(?<=[.!?。！？\n])\s+/u', $text) as $sentence) {
            if ($buf !== '' && mb_strlen($buf.' '.$sentence) > 1000) {
                $parts[] = $buf;
                $buf = '';
            }
            $buf = trim($buf.' '.$sentence);
        }
        if ($buf !== '') {
            $parts[] = $buf;
        }
        foreach ($parts as $p) {
            $mp3 .= Storage::disk('public')->get($this->speech->path($p, $lang));
        }
        $ex = $item->custom_exercise_id;
        $path = "custom-exercises/{$ex}/audio/{$item->id}-ai-".substr(md5($text), 0, 8).'.mp3';
        Storage::disk('local')->put($path, $mp3);
        $meta = [];
        // Giữ file gốc (giáo viên tải lên) để "Dùng lại file gốc".
        if ($item->audio_path && empty($item->meta['audio_ai'])) {
            $meta['audio_original'] = $item->audio_path;
        } elseif ($item->audio_path && $item->audio_path !== $path && ! empty($item->meta['audio_ai'])) {
            Storage::disk('local')->delete($item->audio_path);   // bản giọng AI cũ
        }
        $meta['audio_ai'] = true;
        $this->finish($item, null, ['audio_path' => $path, 'meta' => $meta]);
    }

    /** Bỏ giọng AI, dùng lại file nghe gốc (hoặc không có file → nút "Nghe" đọc bằng giọng AI từng lúc). */
    public function revertAudio(CustomExerciseItem $item): void
    {
        $meta = $item->meta ?? [];
        if (! empty($meta['audio_ai']) && $item->audio_path) {
            Storage::disk('local')->delete($item->audio_path);
        }
        $orig = $meta['audio_original'] ?? null;
        unset($meta['audio_ai'], $meta['audio_original']);
        $item->update(['audio_path' => $orig && Storage::disk('local')->exists($orig) ? $orig : null, 'meta' => $meta]);
    }
}
