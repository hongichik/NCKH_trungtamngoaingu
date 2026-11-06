<?php

namespace App\Support\AiImport;

use App\Models\AiImport;
use App\Models\AiJob;
use App\Models\CourseLesson;
use App\Models\CustomExercise;
use App\Models\Teacher;
use App\Models\User;
use App\Support\Ai\Ai;
use App\Support\Ai\AiException;
use App\Support\CustomExercise\CustomExerciseService;
use App\Support\QuestionTypes\QuestionType;
use App\Support\QuestionTypes\Registry;
use App\Support\QuestionTypes\Scoring;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * AI đọc tài liệu giáo viên → bản thiết kế bài (JSON). AI KHÔNG viết giao diện hay code:
 *  - Lượt 1 (plan): chia tài liệu thành các phần, chọn dạng bài trong danh sách rút gọn (mã + 1 dòng mô tả),
 *    tên file nghe được nhắc tới, cách tính điểm ghi trong tài liệu.
 *  - Lượt 2 (build, mỗi phần một lượt): chỉ gửi lược đồ JSON + ví dụ của ĐÚNG dạng bài đó → nội dung phần.
 *  - Code kiểm tra theo lược đồ (sai thì cho AI sửa 1 lần, vẫn sai thì đánh dấu cho giáo viên), ghép file nghe, hỏi khi thiếu.
 */
class AiImportService
{
    public const MAX_TEXT = 60000;

    public function __construct(private Ai $ai, private AudioLinker $linker) {}

    /** Danh sách rút gọn gửi AI: dạng bài đang bật, đúng ngôn ngữ khoá (không gồm dạng V1). */
    public function menu(AiImport $import): array
    {
        return app(CustomExerciseService::class)->typesFor($import->course)
            ->map(fn (QuestionType $t) => ['code' => $t->code, 'line' => $t->code.' — '.$t->name.' [kỹ năng: '.$t->label('skill').'] ('.$t->engine()->label().($t->description ? '; '.$t->description : '').')'])
            ->values()->all();
    }

    public function start(AiImport $import): void
    {
        // Bước 0: chép lời các file nghe (speech-to-text) để AI chia phần / dựng đề khớp với nội dung bài nghe.
        if ($this->queueTranscripts($import, true)) {
            return;
        }
        $needOcr = collect($import->pages ?? [])->where('ocr', true)->whereNull('done')->values();
        if ($needOcr->isNotEmpty()) {
            $import->update(['status' => 'ocr', 'error' => null]);
            // Các trang OCR song song: mỗi việc nền gửi cùng lúc tối đa ai.concurrency trang.
            foreach ($needOcr->pluck('no')->chunk(max(1, (int) config('ai.concurrency', 8))) as $group) {
                AiJob::dispatch('ai_import_ocr', ['import_id' => $import->id, 'pages' => $group->values()->all()], $import->batch(), 0, $import->lane());
            }

            return;
        }
        $this->plan($import);
    }

    // ---------------------------------------------------------------- chép lời file nghe

    /** Giới hạn file của API chép lời. */
    public const TRANSCRIBE_MAX_BYTES = 25 * 1048576;

    /**
     * Xếp việc chép lời cho các file nghe chưa chép (bỏ qua giọng AI — đã có lời).
     * $blocking = true: lúc bắt đầu dựng, chuyển sang "transcribing" và chờ xong mới chia phần.
     */
    public function queueTranscripts(AiImport $import, bool $blocking = false, ?string $onlyId = null): bool
    {
        $audio = $import->audio ?? [];
        $todo = [];
        foreach ($audio as &$a) {
            if (! empty($a['tts']) || ($onlyId !== null && $a['id'] !== $onlyId) || ($onlyId === null && ! empty($a['transcript_status']))) {
                continue;
            }
            $a['transcript_status'] = 'pending';
            $a['transcript_error'] = null;
            $todo[] = $a['id'];
        }
        unset($a);
        if (! $todo || ! filled(config('ai.openai.key'))) {
            return false;
        }
        $import->update(['audio' => $audio] + ($blocking ? ['status' => 'transcribing', 'error' => null] : []));
        foreach ($todo as $id) {
            AiJob::dispatch('ai_import_transcribe', ['import_id' => $import->id, 'audio_id' => $id], $import->batch(), 0, $import->lane());
        }

        return true;
    }

    /** Chép lời một file nghe. Trả về số ký tự chép được. */
    public function transcribeAudio(AiImport $import, string $audioId): int
    {
        $a = collect($import->audio ?? [])->firstWhere('id', $audioId);
        if (! $a || ! empty($a['tts'])) {
            return 0;
        }
        $path = Storage::disk('local')->path($a['path']);
        if (! is_file($path) || filesize($path) === 0) {
            $this->markTranscript($import, $audioId, 'failed', null, 'Không thấy file hoặc file rỗng.');

            return 0;
        }
        if (filesize($path) > self::TRANSCRIBE_MAX_BYTES) {
            $this->markTranscript($import, $audioId, 'skipped', null, 'File lớn hơn 25 MB nên chưa chép lời tự động được — nén (vd mp3 64 kbps) hoặc cắt nhỏ rồi tải lại.');

            return 0;
        }
        $zh = $import->course->language === 'zh';
        $res = $this->ai->for($this->actorOf($import))->transcribe($path, [
            'prompt' => $zh ? '这是汉语听力练习的录音，可能有越南语说明。' : 'This is an English listening exercise recording. It may contain Vietnamese instructions.',
        ]);
        $text = trim((string) ($res['text'] ?? ''));
        $this->markTranscript($import, $audioId, $text === '' ? 'failed' : 'done', $text ?: null, $text === '' ? 'Không nghe được lời nào trong file.' : null);

        return mb_strlen($text);
    }

    public function markTranscript(AiImport $import, string $audioId, string $status, ?string $text, ?string $error = null): void
    {
        DB::transaction(function () use ($import, $audioId, $status, $text, $error) {
            $fresh = AiImport::whereKey($import->id)->lockForUpdate()->first();
            if (! $fresh) {
                return;
            }
            $audio = $fresh->audio ?? [];
            foreach ($audio as &$a) {
                if ($a['id'] === $audioId) {
                    $a['transcript_status'] = $status;
                    $a['transcript'] = $text !== null ? mb_substr($text, 0, 20000) : ($a['transcript'] ?? null);
                    $a['transcript_error'] = $error;
                }
            }
            unset($a);
            $fresh->update(['audio' => $audio]);
        });
    }

    /** Lúc bắt đầu dựng: file nghe cuối cùng chép xong (hoặc lỗi) → đi tiếp sang đọc trang scan / chia phần. */
    public function continueAfterTranscribe(?AiImport $import): void
    {
        if (! $import || $import->status !== 'transcribing' || collect($import->audio ?? [])->contains('transcript_status', 'pending')) {
            return;
        }
        // Nhiều việc chép lời có thể xong cùng lúc: chỉ một việc được đi tiếp.
        if (AiImport::whereKey($import->id)->where('status', 'transcribing')->update(['status' => 'uploading']) === 1) {
            $this->start($import->fresh());
        }
    }

    /** Lời đã chép của một file nghe (null nếu chưa có). */
    public static function transcriptOf(AiImport $import, ?string $audioId): ?string
    {
        $a = $audioId ? collect($import->audio ?? [])->firstWhere('id', $audioId) : null;

        return $a && ! empty($a['transcript']) ? (string) $a['transcript'] : null;
    }

    /** Khối "lời các file nghe" gửi AI ở lượt chia phần. */
    private function transcriptsBlock(AiImport $import): string
    {
        $lines = collect($import->audio ?? [])->filter(fn ($a) => ! empty($a['transcript']))
            ->map(fn ($a) => "--- File \"{$a['name']}\":\n".mb_substr($a['transcript'], 0, 6000))->implode("\n");

        return $lines === '' ? '' : "\nLỜI CÁC FILE NGHE (máy tự chép từ file giáo viên tải lên, có thể sai chính tả nhỏ). Dùng để biết mỗi phần nghe ứng với file nào: "
            ."audio_file = ĐÚNG tên file có nội dung khớp với phần đó; transcript = lời của file đó nếu tài liệu không in lời:\n".mb_substr($lines, 0, 24000)."\n";
    }

    /** Ghép chữ các trang (sau OCR) rồi xếp việc lượt 1. */
    public function plan(AiImport $import): void
    {
        if ($import->pages) {
            $text = collect($import->pages)->sortBy('no')->map(fn ($p) => trim((string) ($p['text'] ?? '')))->filter()->implode("\n\n");
            $import->text = $text;
        }
        $import->text = mb_substr(trim((string) $import->text), 0, self::MAX_TEXT);
        if (mb_strlen($import->text) < 20) {
            $import->update(['status' => 'failed', 'error' => 'Không đọc được chữ trong tài liệu.']);

            return;
        }
        $import->status = 'planning';
        $import->save();
        AiJob::dispatch('ai_import_plan', ['import_id' => $import->id], $import->batch(), 0, $import->lane());
    }

    /** Kỹ năng được chọn số câu hỏi lớn khi AI tự tạo bài tập. */
    public const COMPOSE_SKILLS = ['reading' => 'Đọc', 'listening' => 'Nghe', 'speaking' => 'Nói', 'writing' => 'Viết', 'vocab' => 'Từ vựng & ngữ pháp'];

    /**
     * AI TỰ TẠO bài tập mới cho bài học theo số câu hỏi lớn từng kỹ năng, dựa vào tên / tóm tắt / nội dung của bài học.
     * Bài tạo xong lưu thành 1 bài tập Nháp cho giáo viên duyệt.
     *
     * @param  array<string, int>  $counts  reading | listening | speaking | writing | vocab => số câu hỏi lớn
     */
    public function composeForLesson(CourseLesson $lesson, array $counts, ?object $actor = null): ?AiImport
    {
        $counts = array_filter(array_map('intval', array_intersect_key($counts, self::COMPOSE_SKILLS)));
        if (! $counts || ! filled(config('ai.openai.key'))) {
            return null;
        }
        $course = $lesson->course;
        $text = "Khoá học: {$course->title}".($course->level ? " (trình độ {$course->level})" : '')."\nBài học: {$lesson->title}\n"
            .($lesson->summary ? "Tóm tắt: {$lesson->summary}\n" : '').($lesson->content ? "Nội dung / ghi chú bài học:\n{$lesson->content}\n" : '');
        $actor ??= User::find($course->created_by);
        $import = AiImport::create([
            'course_id' => $course->id, 'lesson_id' => $lesson->id, 'mode' => 'compose', 'status' => 'planning',
            'title' => $lesson->title, 'source_name' => 'Bài học: '.$lesson->title,
            'text' => mb_substr($text, 0, self::MAX_TEXT), 'pages' => [], 'audio' => [],
            'plan' => ['compose' => $counts],
            'created_by_type' => $actor instanceof Teacher ? 'teacher' : 'user', 'created_by_id' => $actor?->id, 'created_by_name' => $actor?->name ?? 'AI',
        ]);
        AiJob::dispatch('ai_import_plan', ['import_id' => $import->id], $import->batch(), 0, $import->lane());

        return $import;
    }

    /** AI tự tạo: tự lưu các phần hợp lệ vào bài học thành 1 bài tập. */
    public function autoSave(AiImport $import, string $status = 'published'): void
    {
        // Mỗi lần tạo (từ giáo trình hoặc AI tự tạo) = MỘT bài tập, dù gồm nhiều kỹ năng; các phần xếp theo nhóm kỹ năng.
        $order = array_flip(array_keys(self::COMPOSE_SKILLS));
        $valid = collect($import->sections ?? [])->filter(fn ($s) => $s['status'] === 'ok' && ! empty($s['content']))
            ->sortBy(fn ($s, $i) => [$order[self::composeSkill($s['type_code']) ?? 'vocab'] ?? 9, $i])->values();
        $audio = collect($import->audio ?? [])->keyBy('id');
        $ex = null;
        if ($valid->isNotEmpty()) {
            $ex = CustomExercise::create([
                'course_id' => $import->course_id, 'lesson_id' => $import->lesson_id,
                'title' => ($import->mode === 'compose' ? 'AI tạo — ' : '').$import->title,
                'skill' => $this->guessSkill($valid->pluck('type_code')->all()), 'status' => $status, 'source' => 'ai',
                'created_by_type' => $import->mode === 'compose' ? $import->created_by_type : 'user', 'created_by_id' => $import->mode === 'compose' ? $import->created_by_id : null,
                'created_by_name' => $import->mode === 'compose' ? 'AI (theo yêu cầu của '.$import->created_by_name.')' : 'AI (giáo trình)',
            ]);
            foreach ($valid as $i => $sec) {
                $this->createItem($ex, $sec, $i + 1, $audio, $import);
            }
        }
        $skipped = count($import->sections ?? []) - $valid->count();
        $import->update(['status' => 'saved', 'custom_exercise_id' => $ex?->id,
            'error' => $skipped > 0 ? $skipped.' phần AI dựng chưa hợp lệ đã bị bỏ qua.' : null]);
    }

    /** Nhóm kỹ năng khi AI tự tạo: reading | listening | speaking | writing | vocab (gồm ngữ pháp, phát âm). */
    public static function composeSkill(string $code): ?string
    {
        $sk = Registry::find($code)?->skill;

        return match ($sk) {
            null => null, 'grammar', 'vocab', 'pronunciation' => 'vocab', default => $sk
        };
    }

    /**
     * AI tự tạo: số giáo viên nhập = số CÂU HỎI LỚN (số phần) của mỗi kỹ năng; trong mỗi phần có bao nhiêu câu nhỏ tuỳ dạng bài.
     * Server chốt đúng số phần: bỏ phần sai kỹ năng / kỹ năng 0, thêm phần khi AI quên kỹ năng, thừa thì bớt, thiếu thì nhân thêm phần (đề khác).
     */
    private function normalizeCompose(AiImport $import, array $sections): array
    {
        $codes = array_column($this->menu($import), 'code');
        $out = [];
        foreach (array_intersect_key($import->plan['compose'] ?? [], self::COMPOSE_SKILLS) as $skill => $n) {
            $n = (int) $n;
            if ($n < 1) {
                continue;
            }
            $mine = array_values(array_filter($sections, fn ($sec) => self::composeSkill($sec['type_code']) === $skill));
            if (! $mine) {
                $code = collect($codes)->first(fn ($c) => self::composeSkill($c) === $skill);
                if (! $code) {
                    continue;   // khoá không có dạng bài nào của kỹ năng này
                }
                $mine = [['title' => self::COMPOSE_SKILLS[$skill], 'type_code' => $code, 'start_quote' => '', 'end_quote' => '', 'audio_file' => null,
                    'needs_audio' => false, 'transcript' => null, 'points_per_question' => null, 'scale' => null, 'confidence' => 1, 'origin' => 'new',
                    'brief' => 'Câu hỏi lớn kỹ năng '.self::COMPOSE_SKILLS[$skill].' bám sát từ vựng, ngữ pháp, chủ đề của bài học.']];
            }
            $base = $mine;
            for ($k = count($mine); $k < $n; $k++) {
                $copy = $base[$k % count($base)];
                $copy['title'] .= ' ('.($k + 1).')';
                $copy['brief'] .= ' — nội dung / ngữ liệu KHÁC với các phần cùng dạng.';
                $mine[] = $copy;
            }
            $out = array_merge($out, array_slice($mine, 0, $n));
        }

        return $out;
    }

    private function createItem(CustomExercise $ex, array $s, int $position, $audio, AiImport $import): void
    {
        $points = $s['points_per_question'] ?? null;
        $item = $ex->items()->create([
            'position' => $position, 'title' => $s['title'], 'type_code' => $s['type_code'], 'content' => $s['content'],
            'scoring' => array_filter(['points' => $points ? (float) $points : null, 'scale' => in_array($s['scale'] ?? null, array_keys(Scoring::SCALES), true) ? $s['scale'] : null]) ?: null,
            'meta' => ['flags' => $s['flags'] ?? [], 'source' => mb_substr($s['source'] ?? '', 0, 5000), 'ai_import_id' => $import->id],
        ]);
        if (! empty($s['audio_id']) && ($a = $audio[$s['audio_id']] ?? null) && Storage::disk('local')->exists($a['path'])) {
            $dest = "custom-exercises/{$ex->id}/audio/{$item->id}.".pathinfo($a['path'], PATHINFO_EXTENSION);
            Storage::disk('local')->copy($a['path'], $dest);
            $item->update(['audio_path' => $dest]);
        }
        if (! empty($s['image_path']) && Storage::disk('local')->exists($s['image_path'])) {
            $dest = "custom-exercises/{$ex->id}/images/{$item->id}.".pathinfo($s['image_path'], PATHINFO_EXTENSION);
            Storage::disk('local')->copy($s['image_path'], $dest);
            $item->update(['image_path' => $dest]);
        }
    }

    // ---------------------------------------------------------------- lượt 1

    public function runPlan(AiImport $import): void
    {
        $menu = $this->menu($import);
        $codes = array_column($menu, 'code');
        $audioNames = collect($import->audio ?? [])->pluck('name')->implode(', ') ?: '(không có)';
        if ($import->mode === 'compose') {
            $want = collect($import->plan['compose'] ?? [])->map(fn ($n, $k) => (self::COMPOSE_SKILLS[$k] ?? $k).": {$n} câu hỏi lớn")->implode('; ');
            $prompt = 'Hãy TẠO MỚI bộ bài tập '.($import->course->language === 'zh' ? 'tiếng Trung' : 'tiếng Anh')." cho một bài học, dựa vào nội dung bài học bên dưới (chủ đề, từ vựng, ngữ pháp, trình độ). Viết câu hỏi mới.\n"
                ."SỐ CÂU HỎI LỚN YÊU CẦU (phải đúng): {$want}.\n"
                .'Mỗi câu hỏi lớn = MỘT phần (vd một bài đọc kèm các câu hỏi nhỏ, một bài nghe kèm câu hỏi, một bài điền từ, một đề viết, một nhiệm vụ nói); số câu nhỏ bên trong tuỳ dạng bài, thường 3–6. '
                .'Kỹ năng yêu cầu N câu hỏi lớn thì tạo ĐÚNG N phần của kỹ năng đó, các phần khác nhau về nội dung / dạng bài; kỹ năng 0 thì không tạo. Mọi phần: origin = "new", start_quote / end_quote = chuỗi rỗng, '
                ."brief = mô tả rõ phần cần tạo (kỹ năng, nội dung / từ vựng / ngữ pháp dùng, độ khó vừa trình độ).\n"
                .'Chọn dạng bài ĐÚNG kỹ năng trong danh sách (chỉ dùng mã có trong danh sách): Đọc → dạng kỹ năng Đọc (có bài đọc ngắn tự viết); Nghe → dạng kỹ năng Nghe (tự viết lời bài nghe, ghi vào transcript; needs_audio = false vì máy sẽ đọc lời); '
                ."Nói → dạng kỹ năng Nói (mỗi câu là một câu hỏi / nhiệm vụ nói); Viết → dạng kỹ năng Viết; Từ vựng & ngữ pháp → dạng Từ vựng / Ngữ pháp.\n"
                .implode("\n", array_column($menu, 'line'))."\n\n"
                ."audio_file = null; points_per_question / scale = null; confidence 0–1.\n\nNỘI DUNG BÀI HỌC:\n\"\"\"\n{$import->text}\n\"\"\"";
        } else {
            $prompt = 'Đây là tài liệu bài tập giáo viên đã soạn (ngôn ngữ học: '.($import->course->language === 'zh' ? 'tiếng Trung' : 'tiếng Anh').").\n"
                ."Nhiệm vụ: chia tài liệu thành các PHẦN bài tập theo đúng thứ tự trong tài liệu và chọn cho mỗi phần MỘT dạng bài trong danh sách sau (chỉ dùng mã có trong danh sách):\n"
                .implode("\n", array_column($menu, 'line'))."\n\n"
                ."Với mỗi phần trả về:\n"
                ."- start_quote / end_quote: chép NGUYÊN VĂN 5–12 từ đầu tiên và cuối cùng của đoạn tài liệu thuộc phần đó (gồm cả đề, câu hỏi, đáp án / lời thoại nếu ở cuối tài liệu thì để trong phần tương ứng bằng cách ghi transcript).\n"
                ."- audio_file: tên file nghe tài liệu ghi cho phần đó (vd \"track05.mp3\"), không có thì null. needs_audio: phần nghe thì true.\n"
                ."- transcript: lời thoại / bài nghe nếu tài liệu có in (để tạo giọng đọc khi thiếu file), không có thì null.\n"
                ."- points_per_question: số điểm mỗi câu nếu tài liệu ghi (vd \"mỗi câu 0,25 điểm\" → 0.25), không ghi thì null. scale: \"ielts_listening\", \"ielts_reading_academic\", \"ielts_criteria\", \"toeic_listening\", \"toeic_reading\", \"vstep\", \"hsk\", \"ten\" hoặc null nếu tài liệu không nói.\n"
                ."- confidence: 0–1, mức chắc chắn về dạng bài đã chọn. origin: luôn \"textbook\"; brief: chuỗi rỗng.\n"
                ."Mục đáp án (Answer key / Đáp án), mục tự đánh giá (Progress check / checklist), lời dặn chung KHÔNG phải bài tập — không tạo phần cho chúng (đáp án sẽ được dùng ở bước dựng từng phần).\n"
                ."Dấu [Hình N] là vị trí một hình / tranh trong tài liệu (ảnh sẽ được gửi kèm khi dựng từng phần) — giữ nguyên dấu này khi chép start_quote / end_quote.\n"
                ."File nghe giáo viên đã tải lên: {$audioNames}.\n"
                .$this->transcriptsBlock($import)."\n"
                ."TÀI LIỆU:\n\"\"\"\n{$import->text}\n\"\"\"";
        }
        $nullable = fn (string $t) => ['type' => [$t, 'null']];
        $schema = ['type' => 'object', 'properties' => [
            'title' => ['type' => 'string'],
            'sections' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'title' => ['type' => 'string'], 'type_code' => ['type' => 'string', 'enum' => $codes],
                'start_quote' => ['type' => 'string'], 'end_quote' => ['type' => 'string'],
                'audio_file' => $nullable('string'), 'needs_audio' => ['type' => 'boolean'], 'transcript' => $nullable('string'),
                'points_per_question' => $nullable('number'), 'scale' => $nullable('string'), 'confidence' => ['type' => 'number'],
                'origin' => ['type' => 'string', 'enum' => ['textbook', 'new']], 'brief' => ['type' => 'string'],
            ], 'required' => ['title', 'type_code', 'start_quote', 'end_quote', 'audio_file', 'needs_audio', 'transcript', 'points_per_question', 'scale', 'confidence', 'origin', 'brief']]],
        ], 'required' => ['title', 'sections']];

        $res = $this->ai->for($this->actorOf($import))->jsonRaw('analysis', $prompt, $schema, 'exercise_plan', ['timeout' => 60]);
        // Trong lúc chờ AI, lượt dựng có thể đã bị huỷ / xoá → bỏ kết quả, không xếp việc mới.
        if (! $import->fresh() || $import->fresh()->status !== 'planning') {
            return;
        }
        $sections = $this->slice($import->text, $import->mode === 'compose' ? $this->normalizeCompose($import, $res['sections']) : $res['sections']);
        if (! $sections) {
            $import->update(['status' => 'failed', 'error' => 'AI không lập được phần bài tập nào.']);

            return;
        }
        foreach ($sections as &$sec) {
            if ($import->mode === 'compose') {
                $sec['origin'] = 'new';
            }
            if (($sec['origin'] ?? 'textbook') === 'new') {
                // Bài tạo mới: nguồn = yêu cầu của AI + nội dung bài học (không cần tìm vị trí trong tài liệu).
                $sec['source'] = 'YÊU CẦU: '.($sec['brief'] ?? '')."\n\nNỘI DUNG BÀI HỌC:\n".mb_substr($import->text, 0, 12000);
                $sec['flags'] = $sec['plan_flags'] = [];
            }
        }
        unset($sec);
        $audio = $import->audio ?? [];
        foreach ($sections as $i => &$s) {
            $s['audio_id'] = $this->linker->match($s['audio_file'], $audio);
            // Phần nghe mà tài liệu không in lời: dùng lời máy chép từ file nghe đã ghép.
            if (empty($s['transcript']) && ($heard = self::transcriptOf($import, $s['audio_id']))) {
                $s['transcript'] = $heard;
            }
            $s['status'] = 'pending';
            $s['flags'] = $s['flags'] ?? [];
            if ($s['confidence'] < 0.6) {
                $s['flags'][] = 'AI chưa chắc dạng bài (độ tin cậy '.round($s['confidence'] * 100).'%).';
                $s['plan_flags'][] = end($s['flags']);
            }
        }
        unset($s);
        $import->update(['plan' => ['title' => $res['title'], 'count' => count($sections)] + array_intersect_key($import->plan ?? [], array_flip(['compose'])), 'sections' => $sections, 'status' => 'building',
            'title' => $import->title ?: $res['title']]);
        // Mọi phần dựng SONG SONG: mỗi việc nền gửi cùng lúc tối đa ai.concurrency phần.
        foreach (array_chunk(array_keys($sections), max(1, (int) config('ai.concurrency', 8))) as $group) {
            AiJob::dispatch('ai_import_build', ['import_id' => $import->id, 'sections' => $group, 'round' => 1], $import->batch(), 0, $import->lane());
        }
    }

    /** Cắt tài liệu theo câu mở đầu / kết thúc mà AI chép lại (so khớp bỏ khoảng trắng thừa, hoa thường). */
    /**
     * Cắt tài liệu theo câu mở đầu / kết thúc AI chép lại. So khớp "mềm": không phân biệt hoa thường, mọi khoảng trắng / xuống dòng
     * như nhau, chuỗi chấm / gạch dưới / dấu … dài ngắn khác nhau vẫn khớp (bản OCR hay đổi số dấu chấm), nháy cong = nháy thẳng.
     * Không thấy câu kết thúc → dừng ở đầu phần kế tiếp.
     */
    public function slice(string $text, array $sections): array
    {
        $flat = preg_replace('/[ \t]+/u', ' ', $text);
        $lower = mb_strtolower($flat);
        $find = function (string $quote, int $from) use ($lower) {
            $q = mb_strtolower(trim($quote));
            if ($q === '') {
                return false;
            }
            $pattern = '';
            foreach (preg_split('/([\s]+|[._…·\-–—]{3,}|…|[‘’\'"“”])/u', $q, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $tok) {
                $pattern .= match (true) {
                    (bool) preg_match('/^\s+$/u', $tok) => '\s*',
                    (bool) preg_match('/^([._…·\-–—]{3,}|…)$/u', $tok) => '[\s._…·\-–—]*',
                    (bool) preg_match('/^[‘’\'"“”]$/u', $tok) => '[‘’\'"“”]?',
                    default => preg_quote($tok, '/'),
                };
            }
            if (preg_match('/'.$pattern.'/u', $lower, $m, PREG_OFFSET_CAPTURE, strlen(mb_substr($lower, 0, $from)))) {
                return ['start' => mb_strlen(substr($lower, 0, $m[0][1])), 'len' => mb_strlen($m[0][0])];
            }

            return false;
        };

        // Lượt 1: vị trí mở đầu của mọi phần (để làm mốc kết thúc dự phòng).
        $starts = [];
        $cursor = 0;
        foreach ($sections as $i => $s) {
            $st = $find($s['start_quote'], $cursor);
            $starts[$i] = $st;
            if ($st) {
                $cursor = $st['start'] + 1;
            }
        }

        $out = [];
        $cursor = 0;
        foreach ($sections as $i => $s) {
            $st = $starts[$i] ?: false;
            $next = null;
            for ($j = $i + 1; $j < count($sections); $j++) {
                if ($starts[$j]) {
                    $next = $starts[$j]['start'];
                    break;
                }
            }
            $flags = [];
            $from = $st ? $st['start'] : $cursor;
            $en = $find($s['end_quote'], $from);
            if ($en && ($next === null || $en['start'] < $next)) {
                $end = $en['start'] + $en['len'];
            } else {
                $end = $next ?? min(mb_strlen($flat), $from + 4000);
                if ($next === null) {
                    $flags[] = 'Không tìm thấy đoạn kết thúc phần trong tài liệu — kiểm tra lại nội dung.';
                }
            }
            if (! $st) {
                $flags[] = 'Không xác định được vị trí phần này trong tài liệu — kiểm tra lại nội dung.';
            }
            $cursor = $end;
            $out[] = $s + ['source' => trim(mb_substr($flat, $from, max(0, $end - $from))), 'flags' => $flags, 'plan_flags' => $flags];
        }

        return $out;
    }

    /** Lời dặn khi "dựng lại, lấy giáo trình làm mẫu": các quy tắc chép nguyên văn / dùng đáp án có sẵn bên dưới không áp dụng. */
    public const MODEL_MODE_NOTE = 'CHẾ ĐỘ: DỰNG LẠI, LẤY GIÁO TRÌNH LÀM MẪU. Dữ liệu gốc của phần này có thể bị lỗi (chữ OCR sai, mất chữ, thiếu tranh, đáp án mơ hồ / sai). '
        .'Vì vậy KHÔNG chép nguyên văn và KHÔNG phụ thuộc vào đáp án trong tài liệu (bỏ qua các quy tắc "giữ NGUYÊN VĂN" và "PHẢI dùng đáp án có sẵn" bên dưới). '
        .'Hãy TỰ VIẾT LẠI phần này: cùng dạng bài, cùng mục tiêu (chủ đề, từ vựng, ngữ pháp, kỹ năng), cùng số câu và độ khó như đoạn tài liệu mẫu; câu hỏi, lựa chọn, ngữ liệu viết mới, rõ ràng, tự nhiên; '
        .'tự giải và kiểm tra để mỗi câu có đúng một đáp án chính xác. Nếu bài gốc cần tranh mà tranh không có / không rõ, chuyển thành câu hỏi bằng chữ, không phụ thuộc tranh. '
        ."Bài đọc / lời bài nghe dùng trong câu hỏi phải đưa đầy đủ vào stimulus.\n\n";

    /** Giới hạn chữ tài liệu gửi kèm mỗi lượt dựng phần (để tra bài đọc chung và mục đáp án). */
    public const REFERENCE_MAX = 30000;

    /**
     * Toàn bộ tài liệu để AI tra cứu khi dựng một phần: bài đọc / bảng dùng chung giữa các bài, mục đáp án ở cuối tài liệu.
     * Tài liệu dài hơn giới hạn → phần đầu + mục đáp án (code tự tìm tiêu đề "Answer key", "Đáp án", "Key", "答案").
     */
    public function referenceText(AiImport $import): string
    {
        $text = (string) $import->text;
        if (mb_strlen($text) <= self::REFERENCE_MAX) {
            return $text;
        }
        $key = self::answerKeyBlock($text);

        return mb_substr($text, 0, self::REFERENCE_MAX - mb_strlen($key) - 50).($key ? "\n\n[…]\n\n".$key : '');
    }

    /** Mục đáp án của tài liệu (từ dòng tiêu đề đáp án tới hết), tối đa 12.000 ký tự. */
    public static function answerKeyBlock(string $text): string
    {
        if (preg_match('/^[ \t]*(answer\s*keys?|answers|keys?|đáp\s*án|giải\s*đáp|答案|参考答案)\b[^\n]{0,60}$/imu', $text, $m, PREG_OFFSET_CAPTURE)) {
            return mb_substr(substr($text, $m[0][1]), 0, 12000);
        }

        return '';
    }

    /** Lưu ảnh nhúng của file Word (đã chuẩn hoá JPEG) → $import->images = [{n, path}] ứng với dấu "[Hình N]". */
    public function storeDocImages(AiImport $import, array $images): void
    {
        $list = [];
        foreach ($images as $img) {
            $jpeg = DocxReader::normalizeImage($img['bytes']);
            if ($jpeg === null) {
                continue;
            }
            $path = $import->storageDir()."/doc-images/{$img['n']}.jpg";
            Storage::disk('local')->put($path, $jpeg);
            $list[] = ['n' => $img['n'], 'path' => $path];
        }
        $import->update(['images' => $list]);
    }

    /** Ảnh Word thuộc đoạn tài liệu của phần (theo dấu "[Hình N]"), tối đa 8 ảnh. @return array<int, string> n => path */
    public function sectionDocImages(AiImport $import, array $section): array
    {
        preg_match_all('/\[Hình (\d+)\]/u', (string) ($section['source'] ?? ''), $m);
        $byN = collect($import->images ?? [])->keyBy('n');
        $out = [];
        foreach (array_unique(array_map('intval', $m[1])) as $n) {
            if (isset($byN[$n]) && Storage::disk('local')->exists($byN[$n]['path'])) {
                $out[$n] = $byN[$n]['path'];
            }
            if (count($out) >= 8) {
                break;
            }
        }

        return $out;
    }

    /**
     * Ghép nhiều tranh rời thành 1 ảnh, mỗi tranh có số 1, 2, 3… ở góc (cho bài nối tranh / chọn tranh).
     *
     * @param  list<string>  $paths
     */
    public function composeImages(array $paths, string $dest): ?string
    {
        $imgs = array_values(array_filter(array_map(fn ($p) => @imagecreatefromstring((string) Storage::disk('local')->get($p)), $paths)));
        if (! $imgs) {
            return null;
        }
        if (count($imgs) === 1) {
            Storage::disk('local')->copy($paths[0], $dest);

            return $dest;
        }
        $cols = count($imgs) <= 4 ? count($imgs) : 3;
        $cell = 320;
        $pad = 16;
        $rows = (int) ceil(count($imgs) / $cols);
        $canvas = imagecreatetruecolor($cols * ($cell + $pad) + $pad, $rows * ($cell + $pad) + $pad);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $ink = imagecolorallocate($canvas, 10, 74, 117);
        imagefill($canvas, 0, 0, $white);
        foreach ($imgs as $i => $im) {
            [$w, $h] = [imagesx($im), imagesy($im)];
            $scale = min($cell / $w, $cell / $h);
            $nw = (int) ($w * $scale);
            $nh = (int) ($h * $scale);
            $x = $pad + ($i % $cols) * ($cell + $pad) + intdiv($cell - $nw, 2);
            $y = $pad + intdiv($i, $cols) * ($cell + $pad) + intdiv($cell - $nh, 2);
            imagecopyresampled($canvas, $im, $x, $y, 0, 0, $nw, $nh, $w, $h);
            $bx = $pad + ($i % $cols) * ($cell + $pad);
            $by = $pad + intdiv($i, $cols) * ($cell + $pad);
            imagefilledellipse($canvas, $bx + 18, $by + 18, 34, 34, $ink);
            imagestring($canvas, 5, $bx + 18 - (strlen((string) ($i + 1)) * 4), $by + 10, (string) ($i + 1), $white);
        }
        ob_start();
        imagejpeg($canvas, null, 88);
        Storage::disk('local')->put($dest, (string) ob_get_clean());

        return $dest;
    }

    /** Ảnh các trang (trang scan / ảnh chụp) chứa đoạn tài liệu của phần — để AI nhìn tranh, bảng, hình. Tối đa 2 trang. */
    public function sectionPages(AiImport $import, array $section): array
    {
        $norm = fn ($t) => preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower((string) $t));
        $src = $norm($section['source'] ?? '');
        $head = mb_substr($src, 0, 40);
        $tail = mb_substr($src, -40);
        $pages = [];
        foreach (collect($import->pages ?? [])->sortBy('no') as $p) {
            if (empty($p['image']) || ! Storage::disk('local')->exists($p['image'])) {
                continue;
            }
            $t = $norm($p['text'] ?? '');
            if ($head !== '' && (str_contains($t, $head) || str_contains($t, $tail))) {
                $pages[] = $p;
            }
        }

        return array_slice($pages, 0, 2);
    }

    /** Cắt vùng ảnh (toạ độ 0–1) từ ảnh trang → ảnh của phần. */
    public function cropImage(string $pagePath, array $box, string $dest): ?string
    {
        $abs = Storage::disk('local')->path($pagePath);
        $img = @imagecreatefromstring((string) file_get_contents($abs));
        if (! $img || count($box) !== 4) {
            return null;
        }
        [$w, $h] = [imagesx($img), imagesy($img)];
        [$x1, $y1, $x2, $y2] = array_map(fn ($v) => max(0.0, min(1.0, (float) $v)), $box);
        [$x1, $x2] = [min($x1, $x2), max($x1, $x2)];
        [$y1, $y2] = [min($y1, $y2), max($y1, $y2)];
        $pad = 0.015;
        $x = (int) floor(max(0, $x1 - $pad) * $w);
        $y = (int) floor(max(0, $y1 - $pad) * $h);
        $cw = (int) ceil(min(1, $x2 + $pad) * $w) - $x;
        $ch = (int) ceil(min(1, $y2 + $pad) * $h) - $y;
        if ($cw < 40 || $ch < 40) {
            return null;
        }
        $crop = imagecrop($img, ['x' => $x, 'y' => $y, 'width' => $cw, 'height' => $ch]);
        if (! $crop) {
            return null;
        }
        ob_start();
        imagejpeg($crop, null, 85);
        Storage::disk('local')->put($dest, (string) ob_get_clean());

        return $dest;
    }

    // ---------------------------------------------------------------- lượt 2

    public function runBuild(AiImport $import, int $index, int $round): void
    {
        $this->runBuildMany($import, [$index], $round);
    }

    /**
     * Dựng nhiều phần CÙNG LÚC: mọi phần gửi AI song song (Http::pool) trong một việc nền, thay vì mỗi phần một việc
     * xếp hàng lần lượt. Phần sai lược đồ được gửi sửa ngay (vòng 2, cũng song song) chứ không xếp việc mới.
     *
     * @param  list<int>  $indexes
     */
    public function runBuildMany(AiImport $import, array $indexes, int $round = 1): void
    {
        $started = microtime(true);
        while ($indexes) {
            $ctx = [];
            foreach ($indexes as $i) {
                if ($c = $this->buildRequest($import, (int) $i, $round)) {
                    $ctx[(int) $i] = $c;
                }
            }
            if (! $ctx) {
                break;
            }
            $results = $this->ai->for($this->actorOf($import))->jsonRawMany('generate', array_map(fn ($c) => $c['request'], $ctx));
            $import->refresh();
            if (! in_array($import->status, ['building', 'review'], true)) {
                return;   // đã huỷ trong lúc chờ AI
            }
            $retry = [];
            foreach ($results as $i => $res) {
                if ($res instanceof AiException) {
                    $sections = $import->sections;
                    $sections[$i]['status'] = 'invalid';
                    $sections[$i]['errors'] = ['AI lỗi khi dựng phần này: '.mb_substr($res->getMessage(), 0, 200)];
                    $import->update(['sections' => $sections]);

                    continue;
                }
                if ($this->applyBuild($import, $i, $round, $ctx[$i], $res)) {
                    $retry[] = $i;
                }
            }
            if (! $retry) {
                break;
            }
            $round++;
            // Trên web (tick) có giới hạn thời gian: vòng sửa để sang việc nền kế tiếp.
            if (PHP_SAPI !== 'cli' && microtime(true) - $started > 30) {
                AiJob::dispatch('ai_import_build', ['import_id' => $import->id, 'sections' => $retry, 'round' => $round], $import->batch(), 0, $import->lane());

                return;
            }
            $indexes = $retry;
        }
        $this->maybeReview($import);
    }

    /** Lời nhắc + ảnh gửi AI để dựng một phần. null = phần không cần dựng. */
    private function buildRequest(AiImport $import, int $index, int $round): ?array
    {
        $sections = $import->sections;
        $s = $sections[$index] ?? null;
        if (! $s || $s['status'] === 'ok') {
            return null;
        }
        $type = Registry::get($s['type_code']);
        $engine = $type->engine();
        $schema = json_encode($engine->schema($type), JSON_UNESCAPED_UNICODE);
        $sample = json_encode($type->sample, JSON_UNESCAPED_UNICODE);
        $fixed = $type->option('choices') ? "\nLựa chọn cố định của dạng này (dùng đúng các khoá): ".implode(', ', array_keys($type->option('choices'))).'.' : '';
        $isNew = ($s['origin'] ?? 'textbook') === 'new';
        $asModel = ! $isNew && ($s['build_style'] ?? 'strict') === 'model';
        $prompt = ($asModel ? self::MODEL_MODE_NOTE : '')
            .($isNew ? "TẠO MỚI một phần bài tập theo yêu cầu bên dưới, dạng \"{$type->name}\" (mã {$type->code}), dùng đúng từ vựng / ngữ pháp / nội dung của Unit, đáp án phải chính xác. Nếu là bài nghe, viết lời bài nghe vào stimulus.transcript.\n" : '')
            ."Dựng NỘI DUNG cho một phần bài tập theo dạng \"{$type->name}\" (mã {$type->code}).\n"
            ."Cách làm bài: {$engine->interaction()}\nCách chấm: {$engine->rubric($type)}{$fixed}\n"
            ."LƯỢC ĐỒ JSON của nội dung (bắt buộc đúng):\n{$schema}\nVÍ DỤ hợp lệ:\n{$sample}\n\n"
            .'Quy tắc: giữ NGUYÊN VĂN đề, câu hỏi, lựa chọn trong tài liệu; ô trống viết [[1]], [[2]]…; không bịa thêm câu hỏi; không có ảnh thì bỏ trường image. '
            .'Lựa chọn KHÔNG ghi kèm chữ cái / số thứ tự ở đầu ("Six months", không phải "A. Six months") — giao diện tự đánh A, B, C.'."\n"
            .'ĐÁP ÁN: nếu tài liệu có mục đáp án (Answer key / Đáp án / Key… thường ở cuối tài liệu — xem TÀI LIỆU ĐẦY ĐỦ bên dưới) thì PHẢI dùng đúng đáp án đó cho bài này '
            .'(đối chiếu số / tên bài như 1a, 2, Exercise 4, Questions 1–6), KHÔNG tự giải. Chỉ khi tài liệu thật sự không có đáp án cho bài này mới tự giải và ghi vào flags ("Đáp án do AI giải: câu …").'."\n"
            .'NGỮ LIỆU CHUNG: nếu câu hỏi dựa vào bài đọc / bảng / hội thoại / danh sách từ nằm ở chỗ khác trong tài liệu (vd "the text in Exercise 1"), hãy chép NGUYÊN VĂN ngữ liệu đó vào stimulus.passage để học viên đọc được.'."\n"
            .'Lời thoại bài nghe (nếu có) đưa vào stimulus.transcript. Những chỗ chưa chắc (đáp án mơ hồ, chữ bị mất…) ghi vào flags bằng tiếng Việt.'."\n"
            .$this->sectionAudioNote($import, $s)
            .(! empty($s['errors']) && $round > 1 ? 'LẦN TRƯỚC CHƯA ĐẠT, hãy sửa: '.implode('; ', array_slice($s['errors'], 0, 8))."\n" : '')
            ."\nĐOẠN TÀI LIỆU CỦA PHẦN \"{$s['title']}\" (dựng nội dung từ đoạn này):\n\"\"\"\n{$s['source']}\n\"\"\"\n"
            .($isNew ? '' : "\nTÀI LIỆU ĐẦY ĐỦ (chỉ để tra đáp án và ngữ liệu chung, KHÔNG dựng thêm bài khác từ đây):\n\"\"\"\n".$this->referenceText($import)."\n\"\"\"\n")
            ."\nTrả về content_json = chuỗi JSON của nội dung.";
        $pages = $this->sectionPages($import, $s);
        if ($pages) {
            $prompt .= "\n\nKÈM THEO ".count($pages).' ẢNH TRANG GỐC (thứ tự 1..'.count($pages).') chứa phần này: hãy nhìn ảnh để đọc tranh, hình, bảng, chữ OCR bị sót. '
                .'Bài nối tranh / gắn nhãn: dựa vào tranh để ra đáp án; items ghi "Picture 1", "Picture 2"… (hoặc nhãn trên tranh). '
                .'Nếu học viên CẦN nhìn tranh / hình để làm bài, trả image_page = số thứ tự ảnh trang và image_box = [x1, y1, x2, y2] (tỉ lệ 0–1 so với chiều rộng / cao ảnh trang) bao trọn vùng tranh của bài (gồm số thứ tự trên tranh, không lấy chữ đề bài); không cần thì để null.';
        }
        $docImages = $this->sectionDocImages($import, $s);
        if ($docImages) {
            $prompt .= "\n\nKÈM THEO ".count($docImages).' ẢNH NHÚNG TRONG FILE WORD của phần này, theo thứ tự: '.implode(', ', array_map(fn ($n) => "[Hình {$n}]", array_keys($docImages))).'. '
                .'Hãy nhìn ảnh để hiểu tranh / hình và ra đáp án đúng. Nếu học viên CẦN nhìn tranh để làm bài, trả image_refs = danh sách số N của các [Hình N] theo thứ tự sẽ đánh số 1, 2, 3… cho học viên '
                .'(nhiều tranh rời sẽ được ghép thành 1 ảnh có số; items của bài nối ghi "Picture 1", "Picture 2"… tương ứng). Không cần ảnh thì image_refs = null.';
        }

        return [
            'request' => ['prompt' => $prompt, 'name' => 'exercise_section', 'schema' => ['type' => 'object', 'properties' => [
                'content_json' => ['type' => 'string'], 'flags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'image_page' => ['type' => ['integer', 'null']], 'image_box' => ['type' => ['array', 'null'], 'items' => ['type' => 'number']],
                'image_refs' => ['type' => ['array', 'null'], 'items' => ['type' => 'integer']],
            ], 'required' => ['content_json', 'flags', 'image_page', 'image_box', 'image_refs']],
                'extra' => ['timeout' => 90, 'images' => array_merge(array_map(fn ($p) => Storage::disk('local')->path($p['image']), $pages), array_map(fn ($p) => Storage::disk('local')->path($p), array_values($docImages)))]],
            's' => $s, 'pages' => $pages, 'docImages' => $docImages, 'asModel' => $asModel, 'type' => $type, 'engine' => $engine,
        ];
    }

    /** Lời bài nghe của phần: ưu tiên lời máy chép từ chính file nghe đã gắn (khớp nội dung file thật). */
    private function sectionAudioNote(AiImport $import, array $s): string
    {
        $audio = collect($import->audio ?? [])->firstWhere('id', $s['audio_id'] ?? null);
        if ($audio && ! empty($audio['transcript']) && empty($audio['tts'])) {
            return "LỜI FILE NGHE CỦA PHẦN NÀY (máy tự chép từ file \"{$audio['name']}\" học viên sẽ nghe, có thể sai chính tả nhỏ):\n\"\"\"\n".mb_substr($audio['transcript'], 0, 12000)."\n\"\"\"\n"
                .'Đề, câu hỏi và đáp án PHẢI khớp với nội dung bài nghe này (thứ tự thông tin, số liệu, tên riêng). Tài liệu không có đáp án thì dựa vào lời này để giải. '
                ."Tài liệu không in lời thì đưa lời này (đã sửa chính tả) vào stimulus.transcript.\n";
        }

        return ! empty($s['transcript']) ? "Lời thoại bài nghe AI đã thấy ở lượt trước: {$s['transcript']}\n" : '';
    }

    /** Ghi kết quả AI vào phần. Trả về true nếu cần dựng thêm vòng sửa lỗi. */
    private function applyBuild(AiImport $import, int $index, int $round, array $ctx, array $res): bool
    {
        ['s' => $s, 'pages' => $pages, 'docImages' => $docImages, 'asModel' => $asModel, 'type' => $type, 'engine' => $engine] = $ctx;
        $imagePath = $s['image_path'] ?? null;
        $refs = array_values(array_filter(array_map(fn ($n) => $docImages[(int) $n] ?? null, (array) ($res['image_refs'] ?? []))));
        if ($refs && empty($s['image_manual'])) {
            // Ảnh lấy thẳng từ file Word (ghép nhiều tranh thành 1 ảnh có số).
            $imagePath = $this->composeImages($refs, $import->storageDir()."/images/s{$index}-r{$round}-doc.jpg") ?? $imagePath;
        } elseif (! empty($res['image_page']) && ! empty($res['image_box']) && isset($pages[$res['image_page'] - 1]) && empty($s['image_manual'])) {
            $imagePath = $this->cropImage($pages[$res['image_page'] - 1]['image'], $res['image_box'], $import->storageDir()."/images/s{$index}-r{$round}.jpg") ?? $imagePath;
        }

        $content = json_decode($res['content_json'], true);
        $errors = is_array($content) ? $engine->validate($content, $type) : ['AI trả về JSON không đọc được.'];
        $sections = $import->sections;
        // Cờ "chưa chắc" = cờ khi cắt tài liệu + cờ của lượt dựng MỚI NHẤT (không cộng dồn qua các lượt).
        $sections[$index] = array_merge($sections[$index], [
            'content' => is_array($content) ? $content : null, 'errors' => $errors, 'image_path' => $imagePath,
            'flags' => array_values(array_unique(array_merge($sections[$index]['plan_flags'] ?? [], $res['flags'],
                $asModel ? ['AI tự dựng lại theo mẫu giáo trình (không chép nguyên văn) — kiểm tra lại câu hỏi và đáp án.'] : [],
                $imagePath && $imagePath !== ($s['image_path'] ?? null) && ! $refs ? ['Ảnh của phần được cắt tự động từ trang gốc — kiểm tra lại, có thể thay ảnh khác.'] : []))),
            'status' => $errors ? ($round >= 2 ? 'invalid' : 'pending') : 'ok',
        ]);
        $import->sections = $sections;
        $import->save();

        return $errors && $round < 2;
    }

    /** Mọi phần đã dựng xong (đúng hoặc sai sau khi sửa) → chuyển sang chờ duyệt. */
    public function maybeReview(AiImport $import): void
    {
        $import->refresh();
        if ($import->status === 'building' && ! collect($import->sections)->contains('status', 'pending')) {
            if ($import->mode === 'compose') {
                // AI tự tạo: lưu thành 1 bài tập Nháp để giáo viên duyệt.
                $this->autoSave($import, 'draft');

                return;
            }
            $import->update(['status' => 'review']);
        }
    }

    // ---------------------------------------------------------------- duyệt & lưu

    /** Câu hỏi cho giáo viên: phần cần nghe mà chưa có file. */
    public static function missingAudio(AiImport $import): array
    {
        return collect($import->sections ?? [])->filter(fn ($s) => ! empty($s['needs_audio']) && empty($s['audio_id']))->keys()->all();
    }

    /** Lưu bản thiết kế thành bài tập tuỳ chỉnh (nháp) trong thư viện của khoá. */
    public function save(AiImport $import, object $actor): CustomExercise
    {
        $sections = collect($import->sections);
        if ($bad = $sections->filter(fn ($s) => $s['status'] !== 'ok' || empty($s['content']))->keys()->all()) {
            throw new \InvalidArgumentException('Còn phần chưa hợp lệ: '.implode(', ', array_map(fn ($i) => $i + 1, $bad)).'. Sửa nội dung trước khi lưu.');
        }
        $ex = CustomExercise::create([
            'course_id' => $import->course_id, 'lesson_id' => $import->lesson_id, 'title' => $import->title, 'skill' => $this->guessSkill($sections->pluck('type_code')->all()),
            'status' => 'draft', 'source' => 'ai', 'source_classroom_id' => $import->classroom_id,
        ] + (new CustomExercise)->createdBy($actor));
        $audio = collect($import->audio ?? [])->keyBy('id');
        foreach ($sections->values() as $i => $s) {
            $this->createItem($ex, $s, $i + 1, $audio, $import);
        }
        $import->update(['status' => 'saved', 'custom_exercise_id' => $ex->id]);

        return $ex;
    }

    private function guessSkill(array $codes): string
    {
        $skills = collect($codes)->map(fn ($c) => Registry::find($c)?->skill)->filter()->unique();

        return $skills->count() === 1 && array_key_exists($skills->first(), CustomExercise::SKILLS) ? $skills->first() : 'mixed';
    }

    /** Tạo giọng đọc AI từ lời thoại của phần (khi thiếu file nghe và giáo viên đồng ý). */
    public function tts(AiImport $import, int $index): void
    {
        $s = $import->sections[$index];
        $text = trim((string) ($s['transcript'] ?? ($s['content']['stimulus']['transcript'] ?? '')));
        if ($text === '') {
            throw new \InvalidArgumentException('Phần này không có lời thoại để tạo giọng đọc.');
        }
        $voice = $import->course->language === 'zh' ? 'nova' : 'alloy';
        $mp3 = '';
        foreach (array_filter(preg_split('/(?<=[.!?。！？])\s+/u', $text)) as $chunk) {
            $mp3 .= $this->ai->for($this->actorOf($import))->speak($chunk, $voice);
        }
        $id = 'tts'.$index.Str::lower(Str::random(4));
        $path = $import->storageDir()."/audio/{$id}.mp3";
        Storage::disk('local')->put($path, $mp3);
        $import->refresh();
        $audio = $import->audio ?? [];
        $audio[] = ['id' => $id, 'name' => 'Giọng AI — '.$s['title'].'.mp3', 'path' => $path, 'size' => strlen($mp3), 'tts' => true];
        $sections = $import->sections;
        $sections[$index]['audio_id'] = $id;
        $import->update(['audio' => $audio, 'sections' => $sections]);
    }

    public function actorOf(AiImport $import): ?object
    {
        return $import->created_by_type === 'teacher' ? Teacher::find($import->created_by_id) : User::find($import->created_by_id);
    }
}
