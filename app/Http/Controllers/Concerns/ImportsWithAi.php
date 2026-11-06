<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AiImport;
use App\Models\AiJob;
use App\Models\Course;
use App\Models\Teacher;
use App\Support\Ai\Jobs\AiJobRunner;
use App\Support\AiImport\AiImportService;
use App\Support\AiImport\DocxReader;
use App\Support\AiImport\TemplateDocx;
use App\Support\ChunkedUpload;
use App\Support\CustomExercise\CustomExerciseService;
use App\Support\Video\VideoResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Dựng bài bằng AI": tải file Word / PDF / ảnh + file nghe → AI trả bản thiết kế → giáo viên duyệt, sửa → lưu thành bài tập tuỳ chỉnh.
 * Dùng trong controller thư viện bài tập tuỳ chỉnh (cùng quyền: canUseLibrary, actor, customLayout…).
 */
trait ImportsWithAi
{
    private function aiPrefix(): string
    {
        return str_replace('.custom', '.ai-import', $this->customRoutePrefix());
    }

    private function importOf(Course $course, AiImport $import): AiImport
    {
        abort_unless($this->canUseLibrary($course), 403);
        abort_unless($import->course_id === $course->id, 404);
        $actor = $this->actor();
        $mine = $import->created_by_type === ($actor instanceof Teacher ? 'teacher' : 'user') && (int) $import->created_by_id === (int) $actor->id;
        abort_unless($mine || $this->isAdminSide(), 404);

        return $import;
    }

    public function aiCreate(Request $request, Course $course): View
    {
        abort_unless($this->canUseLibrary($course), 403);
        $lesson = $course->lessons()->findOrFail($request->integer('lesson'));

        return view('custom.ai.create', [
            'layout' => $this->customLayout(), 'prefix' => $this->customRoutePrefix(), 'ai' => $this->aiPrefix(), 'course' => $course, 'lesson' => $lesson,
            'classrooms' => $this->isAdminSide() || auth()->user()->canEditCourse($course) ? collect() : $this->assignableClassrooms($course),
            'aiReady' => filled(config('ai.openai.key')),
            'recent' => AiImport::where('lesson_id', $lesson->id)->where('mode', 'extract')->latest('id')->limit(10)->get(),
        ]);
    }

    public function aiStore(Request $request, Course $course): JsonResponse
    {
        abort_unless($this->canUseLibrary($course), 403);
        $data = $request->validate(['title' => ['nullable', 'string', 'max:200'], 'classroom_id' => ['nullable', 'integer'], 'source_name' => ['nullable', 'string', 'max:255'],
            'lesson_id' => ['required', 'integer']]);
        $lesson = $course->lessons()->findOrFail($data['lesson_id']);
        $classroomId = $data['classroom_id'] ?? null;
        if ($classroomId) {
            abort_unless($this->assignableClassrooms($course)->contains('id', $classroomId), 403);
        } else {
            abort_unless($this->isAdminSide() || auth()->user()->canEditCourse($course), 403, 'Bài tập chung của khoá cần quyền sửa khoá; hãy chọn lớp của bạn.');
        }
        $actor = $this->actor();
        $import = AiImport::create(['course_id' => $course->id, 'lesson_id' => $lesson->id, 'mode' => 'extract', 'classroom_id' => $classroomId, 'title' => ($data['title'] ?? '') ?: 'Bài tập — '.$lesson->title,
            'source_name' => $data['source_name'] ?? null, 'status' => 'uploading']
            + ['created_by_type' => $actor instanceof Teacher ? 'teacher' : 'user', 'created_by_id' => $actor->id, 'created_by_name' => $actor->name]);

        return response()->json(['ok' => true, 'id' => $import->id,
            'document' => route($this->aiPrefix().'.document', [$course, $import]), 'audio' => route($this->aiPrefix().'.audio', [$course, $import]),
            'begin' => route($this->aiPrefix().'.begin', [$course, $import]), 'show' => route($this->aiPrefix().'.show', [$course, $import])]);
    }

    /**
     * Tài liệu: file .docx / .txt (đọc trên máy chủ), hoặc pages[] chữ từng trang PDF (trình duyệt đọc bằng pdf.js),
     * hoặc image + no: ảnh trang scan / ảnh chụp cần OCR.
     */
    public function aiDocument(Request $request, Course $course, AiImport $import): JsonResponse
    {
        $this->importOf($course, $import);
        abort_unless($import->status === 'uploading', 409);
        if ($request->hasFile('file')) {
            $request->validate(['file' => ['file', 'max:7900', 'extensions:docx,txt']]);
            $file = $request->file('file');
            try {
                // Word: lấy cả chữ lẫn ảnh nhúng (dấu "[Hình N]" đúng vị trí) để AI nhìn được tranh của từng bài.
                $doc = strtolower($file->getClientOriginalExtension()) === 'docx' ? app(DocxReader::class)->read($file->getRealPath()) : ['text' => (string) file_get_contents($file->getRealPath()), 'images' => []];
            } catch (InvalidArgumentException $e) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }
            $import->update(['text' => mb_substr($doc['text'], 0, AiImportService::MAX_TEXT), 'source_name' => $file->getClientOriginalName()]);
            app(AiImportService::class)->storeDocImages($import, $doc['images']);

            return response()->json(['ok' => true, 'chars' => mb_strlen($doc['text']), 'images' => count($import->fresh()->images ?? [])]);
        }
        if ($request->hasFile('image')) {
            $request->validate(['image' => ['image', 'max:7900'], 'no' => ['required', 'integer', 'min:1', 'max:300'], 'text' => ['nullable', 'string', 'max:30000']]);
            $path = $request->file('image')->storeAs($import->storageDir().'/pages', 'p'.$request->integer('no').'.'.$request->file('image')->extension(), 'local');
            $pages = collect($import->pages ?? [])->reject(fn ($p) => (int) $p['no'] === $request->integer('no'))->values()->all();
            // Trang có chữ nhưng có hình (PDF thường): giữ chữ, ảnh trang chỉ để AI nhìn hình. Trang scan: OCR.
            $text = trim((string) $request->input('text'));
            $pages[] = mb_strlen(preg_replace('/\s+/u', '', $text)) >= 30
                ? ['no' => $request->integer('no'), 'text' => $text, 'ocr' => false, 'image' => $path]
                : ['no' => $request->integer('no'), 'text' => '', 'ocr' => true, 'image' => $path];
            $import->update(['pages' => $pages]);

            return response()->json(['ok' => true]);
        }
        $data = $request->validate(['pages' => ['required', 'array', 'max:300'], 'pages.*.no' => ['required', 'integer', 'min:1'], 'pages.*.text' => ['nullable', 'string', 'max:30000']]);
        $pages = collect($import->pages ?? [])->keyBy('no');
        foreach ($data['pages'] as $p) {
            $pages[(int) $p['no']] = ['no' => (int) $p['no'], 'text' => (string) ($p['text'] ?? ''), 'ocr' => false];
        }
        $import->update(['pages' => $pages->values()->all()]);

        return response()->json(['ok' => true]);
    }

    /** File nghe: tải theo mảnh (mp3 có thể > 8 MB). */
    public function aiAudio(Request $request, Course $course, AiImport $import, ChunkedUpload $upload): JsonResponse
    {
        $this->importOf($course, $import);
        $name = basename((string) $request->query('name'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (! in_array($ext, ['mp3', 'm4a', 'wav', 'ogg', 'aac'], true)) {
            return response()->json(['ok' => false, 'message' => 'Chỉ nhận file nghe mp3, m4a, wav, ogg, aac.'], 422);
        }
        $uploadId = (string) $request->query('upload');
        abort_unless(preg_match('/^[a-z0-9]{8,40}$/', $uploadId), 422);
        $key = "aiimp-{$import->id}-{$uploadId}";
        try {
            $path = $upload->receive($request, $key, (int) $request->query('part'), (int) $request->query('total'), 200 * 1048576);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        if ($path === null) {
            return response()->json(['ok' => true, 'complete' => false]);
        }
        $id = 'a'.Str::lower(Str::random(8));
        $dest = $import->storageDir()."/audio/{$id}.{$ext}";
        Storage::disk('local')->makeDirectory(dirname($dest));
        File::move($path, Storage::disk('local')->path($dest));
        ChunkedUpload::cleanup($key);
        $import->refresh();
        $audio = $import->audio ?? [];
        $audio[] = ['id' => $id, 'name' => mb_substr($name, 0, 200), 'path' => $dest, 'size' => Storage::disk('local')->size($dest)];
        $import->update(['audio' => $audio]);

        return response()->json(['ok' => true, 'complete' => true, 'id' => $id]);
    }

    public function aiBegin(Course $course, AiImport $import, AiImportService $service): JsonResponse
    {
        $this->importOf($course, $import);
        if (! filled(config('ai.openai.key'))) {
            return response()->json(['ok' => false, 'message' => 'Chưa cấu hình AI (Quản trị hệ thống › AI).'], 422);
        }
        if ($import->status === 'uploading') {
            if (! $import->text && ! $import->pages) {
                return response()->json(['ok' => false, 'message' => 'Chưa có tài liệu.'], 422);
            }
            $service->start($import);
        }

        return response()->json(['ok' => true, 'url' => route($this->aiPrefix().'.show', [$course, $import])]);
    }

    public function aiShow(Course $course, AiImport $import): View
    {
        $this->importOf($course, $import);

        return view('custom.ai.show', [
            'layout' => $this->customLayout(), 'prefix' => $this->customRoutePrefix(), 'ai' => $this->aiPrefix(), 'course' => $course, 'import' => $import,
            'types' => app(CustomExerciseService::class)->typesFor($course), 'missing' => AiImportService::missingAudio($import),
            'ttsReady' => filled(config('ai.openai.key')),
        ]);
    }

    /** Trạng thái + chạy hàng đợi của chính người này (dự phòng khi hosting chưa cài cron). */
    public function aiTick(Course $course, AiImport $import, AiJobRunner $runner): JsonResponse
    {
        $this->importOf($course, $import);
        $busy = fn (AiImport $x) => in_array($x->status, AiImport::RUNNING, true)
            || collect($x->sections ?? [])->contains('status', 'pending') || collect($x->audio ?? [])->contains('transcript_status', 'pending');
        if ($busy($import)) {
            $runner->work(20, 3, null, $import->lane());
        }
        $import->refresh();
        $sections = collect($import->sections ?? []);

        return response()->json(['status' => $import->status, 'label' => AiImport::STATUSES[$import->status], 'error' => $import->error,
            'done' => $sections->whereIn('status', ['ok', 'invalid'])->count(), 'total' => $sections->count(),
            // Trạng thái từng phần + từng file nghe: màn duyệt chỉ nạp lại đúng phần / file vừa xong.
            'sections' => $sections->pluck('status')->values(),
            'audio' => collect($import->audio ?? [])->mapWithKeys(fn ($a) => [$a['id'] => $a['transcript_status'] ?? null]),
            'pending_jobs' => AiJob::where('batch', $import->batch())->whereIn('status', [AiJob::PENDING, 'running'])->count()]);
    }

    public function aiUpdateSection(Request $request, Course $course, AiImport $import, int $section): RedirectResponse
    {
        $this->importOf($course, $import);
        $sections = $import->sections;
        abort_unless(isset($sections[$section]) && $import->status === 'review', 404);
        $types = app(CustomExerciseService::class)->typesFor($course)->keys()->all();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'type_code' => ['required', 'in:'.implode(',', $types)],
            'content' => ['required', 'string', 'max:200000'], 'audio_id' => ['nullable', 'string', 'max:40'],
            'points_per_question' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
        ]);
        $content = json_decode($data['content'], true);
        $errors = app(CustomExerciseService::class)->validateItem($data['type_code'], $content);
        $audioIds = collect($import->audio ?? [])->pluck('id')->all();
        $sections[$section] = array_merge($sections[$section], [
            'title' => $data['title'], 'type_code' => $data['type_code'], 'content' => is_array($content) ? $content : $sections[$section]['content'],
            'errors' => $errors, 'status' => $errors ? 'invalid' : 'ok', 'points_per_question' => $data['points_per_question'] ?? null,
            'audio_id' => in_array($data['audio_id'] ?? null, $audioIds, true) ? $data['audio_id'] : null,
        ]);
        $import->update(['sections' => $sections]);

        return redirect()->to(route($this->aiPrefix().'.show', [$course, $import]).'#section-'.$section)
            ->with('status', $errors ? 'Đã lưu nhưng nội dung còn lỗi lược đồ.' : 'Đã lưu phần '.($section + 1).'.');
    }

    /** Giáo viên tải file nghe cho phần đang thiếu. */
    public function aiSectionAudio(Request $request, Course $course, AiImport $import, int $section): RedirectResponse
    {
        $this->importOf($course, $import);
        $request->validate(['audio' => ['required', 'file', 'max:7900', 'extensions:mp3,m4a,wav,ogg,aac']]);
        $file = $request->file('audio');
        $id = 'a'.Str::lower(Str::random(8));
        $path = $file->storeAs($import->storageDir().'/audio', $id.'.'.strtolower($file->getClientOriginalExtension()), 'local');
        $audio = $import->audio ?? [];
        $audio[] = ['id' => $id, 'name' => $file->getClientOriginalName(), 'path' => $path, 'size' => $file->getSize()];
        $sections = $import->sections;
        $sections[$section]['audio_id'] = $id;
        $import->update(['audio' => $audio, 'sections' => $sections]);
        app(AiImportService::class)->queueTranscripts($import->fresh(), false, $id);

        return redirect()->to(route($this->aiPrefix().'.show', [$course, $import]).'#section-'.$section)
            ->with('status', 'Đã gắn file nghe. Máy đang chép lời file — xong thì bấm "Dựng lại" để AI soạn đề khớp với bài nghe.');
    }

    /** Thay / gỡ ảnh của phần (tranh để nối, bản đồ…). */
    public function aiSectionImage(Request $request, Course $course, AiImport $import, int $section): RedirectResponse
    {
        $this->importOf($course, $import);
        $sections = $import->sections;
        abort_unless(isset($sections[$section]), 404);
        if ($request->boolean('remove')) {
            $sections[$section]['image_path'] = null;
        } else {
            $request->validate(['image' => ['required', 'image', 'max:7900']]);
            $sections[$section]['image_path'] = $request->file('image')->storeAs($import->storageDir().'/images', "s{$section}-manual-".time().'.'.$request->file('image')->extension(), 'local');
        }
        $sections[$section]['image_manual'] = true;
        $import->update(['sections' => $sections]);

        return redirect()->to(route($this->aiPrefix().'.show', [$course, $import]).'#section-'.$section)->with('status', 'Đã cập nhật ảnh của phần '.($section + 1).'.');
    }

    public function aiSectionImageFile(Course $course, AiImport $import, int $section): BinaryFileResponse
    {
        $this->importOf($course, $import);
        $path = $import->sections[$section]['image_path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store']);
    }

    /** Giáo viên đồng ý tạo giọng đọc AI từ lời thoại trong tài liệu. */
    public function aiSectionTts(Course $course, AiImport $import, int $section): RedirectResponse
    {
        $this->importOf($course, $import);
        AiJob::dispatch('ai_import_tts', ['import_id' => $import->id, 'section' => $section], $import->batch(), 0, $import->lane());
        app(AiJobRunner::class)->work(20, 1, null, $import->lane());

        return redirect()->to(route($this->aiPrefix().'.show', [$course, $import]).'#section-'.$section)->with('status', 'Đã yêu cầu tạo giọng đọc AI cho phần '.($section + 1).'.');
    }

    /**
     * Dựng lại một phần. style = strict: dựng theo giáo trình (chép nguyên văn, dùng đáp án có sẵn);
     * style = model: lấy giáo trình làm mẫu, AI tự viết lại (khi dữ liệu gốc có thể lỗi: OCR sai, mất chữ, thiếu tranh…).
     */
    public function aiRebuild(Request $request, Course $course, AiImport $import, int $section): RedirectResponse|JsonResponse
    {
        $this->importOf($course, $import);
        $style = $request->input('style') === 'model' ? 'model' : 'strict';
        abort_unless(isset($import->sections[$section]), 404);
        $this->queueRebuild($import, [$section], $style);
        $message = 'Đang dựng lại phần '.($section + 1).($style === 'model' ? ' (AI tự viết lại, lấy giáo trình làm mẫu).' : ' (theo giáo trình).');
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'section' => $section, 'message' => $message]);
        }

        return redirect()->to(route($this->aiPrefix().'.show', [$course, $import]).'#section-'.$section)->with('status', $message);
    }

    /** Dựng lại TOÀN BỘ các phần (giữ nguyên cách chia phần, thứ tự, file nghe, ảnh đã chọn tay). */
    public function aiRebuildAll(Request $request, Course $course, AiImport $import): RedirectResponse|JsonResponse
    {
        $this->importOf($course, $import);
        abort_unless($import->status === 'review', 409, 'Chỉ dựng lại được khi đang ở bước duyệt.');
        $style = $request->input('style') === 'model' ? 'model' : 'strict';
        // Phần đang dựng dở thì để nguyên (việc nền của nó vẫn chạy).
        $indexes = collect($import->sections ?? [])->reject(fn ($s) => $s['status'] === 'pending')->keys()->all();
        $this->queueRebuild($import, $indexes, $style);
        $message = 'Đang dựng lại '.count($indexes).' phần'.($style === 'model' ? ' (AI tự viết lại, lấy giáo trình làm mẫu).' : ' (theo giáo trình).');
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'sections' => $indexes, 'message' => $message]);
        }

        return redirect()->route($this->aiPrefix().'.show', [$course, $import])->with('status', $message);
    }

    /** Đánh dấu các phần "đang dựng" + cắt lại đoạn tài liệu, rồi xếp việc dựng (song song theo nhóm). */
    private function queueRebuild(AiImport $import, array $indexes, string $style): void
    {
        if (! $indexes) {
            return;
        }
        $sections = $import->sections;
        // Cắt lại đoạn tài liệu theo quy tắc hiện hành (khớp mềm chuỗi chấm / xuống dòng, dừng ở đầu phần sau).
        $sliced = app(AiImportService::class)->slice((string) $import->text, array_map(fn ($x) => array_diff_key($x, array_flip(['source', 'flags', 'plan_flags'])), $sections));
        foreach ($indexes as $i) {
            if (($sections[$i]['origin'] ?? 'textbook') !== 'new' && isset($sliced[$i])) {
                $sections[$i]['source'] = $sliced[$i]['source'];
                $sections[$i]['plan_flags'] = $sliced[$i]['plan_flags'];
                $sections[$i]['flags'] = $sliced[$i]['plan_flags'];
            }
            $sections[$i]['status'] = 'pending';
            $sections[$i]['build_style'] = $style;
        }
        // Đang ở màn duyệt: giữ nguyên trạng thái "Chờ duyệt", chỉ các phần này chuyển sang "đang dựng" (các phần khác vẫn hiện bình thường).
        $import->update(['sections' => $sections, 'status' => $import->status === 'review' ? 'review' : 'building']);
        foreach (array_chunk(array_values($indexes), max(1, (int) config('ai.concurrency', 8))) as $group) {
            AiJob::dispatch('ai_import_build', ['import_id' => $import->id, 'sections' => $group, 'round' => 1], $import->batch(), 0, $import->lane());
        }
    }

    /** Lỗi khi đổi thứ tự / xoá phần: chỉ làm được ở màn duyệt và khi không có phần nào đang dựng (chỉ số phần sẽ lệch). */
    private function sectionsLocked(AiImport $import): ?string
    {
        if ($import->status !== 'review') {
            return 'Chỉ đổi thứ tự / xoá phần được khi đang ở bước duyệt.';
        }

        return collect($import->sections ?? [])->contains('status', 'pending') ? 'Đang có phần dựng lại — chờ xong rồi hãy đổi thứ tự / xoá phần.' : null;
    }

    /** Trả lỗi / kết quả cho đổi thứ tự & xoá: JSON khi gọi ngầm từ màn duyệt, chuyển trang khi gửi form thường. */
    private function sectionReply(Request $request, Course $course, AiImport $import, ?string $error, ?string $message = null, int $anchor = 0): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return $error ? response()->json(['ok' => false, 'message' => $error], 422)
                : response()->json(['ok' => true, 'message' => $message, 'titles' => collect($import->fresh()->sections)->pluck('title')]);
        }
        if ($error) {
            return back()->withErrors(['save' => $error]);
        }

        return redirect()->to(route($this->aiPrefix().'.show', [$course, $import]).'#section-'.$anchor)->with('status', $message);
    }

    /** Đổi thứ tự xuất hiện của phần (câu hỏi lớn): lên / xuống một bậc. */
    public function aiMoveSection(Request $request, Course $course, AiImport $import, int $section): RedirectResponse|JsonResponse
    {
        $this->importOf($course, $import);
        $error = null;
        $to = $section;
        $title = '';
        DB::transaction(function () use ($import, $section, $request, &$error, &$to, &$title) {
            $import = AiImport::whereKey($import->id)->lockForUpdate()->first();
            $sections = $import->sections ?? [];
            abort_unless(isset($sections[$section]), 404);
            if ($error = $this->sectionsLocked($import)) {
                return;
            }
            $to = $section + ($request->input('dir') === 'up' ? -1 : 1);
            if (! isset($sections[$to])) {
                $error = 'Phần này đã ở '.($to < 0 ? 'đầu' : 'cuối').' bài.';

                return;
            }
            [$sections[$section], $sections[$to]] = [$sections[$to], $sections[$section]];
            $title = $sections[$to]['title'];
            $import->update(['sections' => array_values($sections)]);
        });

        return $this->sectionReply($request, $course, $import, $error, 'Đã chuyển phần "'.$title.'" sang vị trí '.($to + 1).'.', $to);
    }

    /** Xoá bớt một phần (câu hỏi lớn) khỏi bài trước khi lưu. */
    public function aiDeleteSection(Request $request, Course $course, AiImport $import, int $section): RedirectResponse|JsonResponse
    {
        $this->importOf($course, $import);
        $error = null;
        $title = '';
        DB::transaction(function () use ($import, $section, &$error, &$title) {
            $import = AiImport::whereKey($import->id)->lockForUpdate()->first();
            $sections = $import->sections ?? [];
            abort_unless(isset($sections[$section]), 404);
            if ($error = $this->sectionsLocked($import)) {
                return;
            }
            if (count($sections) <= 1) {
                $error = 'Bài phải còn ít nhất một phần. Muốn bỏ hết thì bấm "Xoá" yêu cầu này.';

                return;
            }
            $title = $sections[$section]['title'];
            array_splice($sections, $section, 1);
            $import->update(['sections' => $sections]);
        });

        return $this->sectionReply($request, $course, $import, $error, 'Đã xoá phần "'.$title.'".', max(0, $section - 1));
    }

    /** Chép lời (lại) một file nghe để AI soạn đề khớp nội dung bài nghe. */
    public function aiTranscribe(Course $course, AiImport $import, string $audioId, AiImportService $service): RedirectResponse
    {
        $this->importOf($course, $import);
        abort_unless(collect($import->audio ?? [])->firstWhere('id', $audioId), 404);
        if (! $service->queueTranscripts($import, false, $audioId)) {
            return back()->withErrors(['save' => 'Chưa cấu hình AI nên chưa chép lời được.']);
        }

        return back()->with('status', 'Máy đang chép lời file nghe — xong thì bấm "Dựng lại" ở phần dùng file này để AI soạn đề khớp với bài nghe.');
    }

    public function aiAudioFile(Course $course, AiImport $import, string $audioId): BinaryFileResponse
    {
        $this->importOf($course, $import);
        $a = collect($import->audio ?? [])->firstWhere('id', $audioId);
        abort_unless($a, 404);

        return VideoResponse::file(Storage::disk('local')->path($a['path']));
    }

    public function aiSave(Request $request, Course $course, AiImport $import, AiImportService $service): RedirectResponse
    {
        $this->importOf($course, $import);
        if ($request->filled('title')) {
            $import->update(['title' => mb_substr($request->input('title'), 0, 200)]);
        }
        try {
            $ex = $service->save($import, $this->actor());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['save' => $e->getMessage()]);
        }

        return redirect()->route($this->customRoutePrefix().'.edit', [$course, $ex])->with('status', 'Đã lưu thành bài tập tuỳ chỉnh (nháp). Kiểm tra lần cuối rồi chuyển sang "Dùng được".');
    }

    public function aiDestroy(Request $request, Course $course, AiImport $import): RedirectResponse
    {
        $this->importOf($course, $import);
        AiJob::where('batch', $import->batch())->where('status', AiJob::PENDING)->delete();
        Storage::disk('local')->deleteDirectory($import->storageDir());
        $import->delete();
        if ($request->input('from') === 'lesson' && $import->lesson_id) {
            return redirect()->route($this->isAdminSide() ? 'admin.courses.lessons.edit' : 'teacher.lessons.edit', [$course, $import->lesson_id])->with('status', 'Đã xoá bản AI dựng.');
        }

        return redirect()->route($this->aiPrefix().'.create', [$course, 'lesson' => $import->lesson_id])->with('status', 'Đã xoá.');
    }

    /** File Word mẫu hướng dẫn giáo viên soạn để AI đọc chính xác nhất. */
    public function aiTemplate(): Response
    {
        return response(TemplateDocx::build(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="mau-de-cho-ai.docx"',
        ]);
    }
}
