<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AiImport;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CustomAttempt;
use App\Models\LessonMaterial;
use App\Models\LessonVideo;
use App\Models\Teacher;
use App\Support\AiImport\AiImportService;
use App\Support\ChunkedUpload;
use App\Support\Video\VideoGuard;
use App\Support\Vocab\LessonVocabulary;
use App\Support\Video\VideoResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Soạn bài học của khoá (dùng chung cho trang quản trị và cổng giáo viên có quyền sửa khoá):
 * danh sách + sắp xếp, nội dung, video (MP4 tải lên theo mảnh / gói HLS mã hoá / Bunny), phụ đề, tài liệu.
 * Lớp dùng trait khai báo: lessonLayout(), lessonRoutePrefix(), authorizeCourse(Course).
 */
trait EditsLessons
{
    abstract protected function lessonLayout(): string;

    abstract protected function lessonRoutePrefix(): string;

    abstract protected function authorizeCourse(Course $course): void;

    private const VIDEO_EXT = ['mp4', 'm4v', 'webm', 'mov'];

    private const HLS_EXT = ['m3u8', 'ts', 'm4s', 'mp4', 'key', 'aac', 'vtt'];

    private function lessonOf(Course $course, CourseLesson $lesson): CourseLesson
    {
        $this->authorizeCourse($course);
        abort_unless($lesson->course_id === $course->id, 404);

        return $lesson;
    }

    public function index(Course $course): View
    {
        $this->authorizeCourse($course);

        $lessons = $course->lessons()->withCount(['videos', 'materials', 'exercises' => fn ($q) => $q->whereNull('source_classroom_id'), 'exercises as all_exercises_count'])->get();

        return view('lessons.editor.index', [
            'layout' => $this->lessonLayout(), 'prefix' => $this->lessonRoutePrefix(), 'course' => $course,
            'lessons' => $lessons, 'attempts' => self::attemptCounts($lessons->pluck('id')->all()),
        ]);
    }

    /** Số lượt làm bài của học viên theo bài học (cảnh báo trước khi xoá). @return array<int, int> */
    private static function attemptCounts(array $lessonIds): array
    {
        return $lessonIds ? CustomAttempt::query()
            ->join('classroom_custom_exercises as cce', 'cce.id', '=', 'custom_attempts.classroom_custom_exercise_id')
            ->join('custom_exercises as ce', 'ce.id', '=', 'cce.custom_exercise_id')
            ->whereIn('ce.lesson_id', $lessonIds)
            ->selectRaw('ce.lesson_id, COUNT(*) AS c')->groupBy('ce.lesson_id')->pluck('c', 'ce.lesson_id')->map(fn ($c) => (int) $c)->all() : [];
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);
        $data = $this->lessonData($request, $course);
        $lesson = $course->lessons()->create($data + ['position' => (int) $course->lessons()->max('position') + 1]);

        return redirect()->route($this->lessonRoutePrefix().'.edit', [$course, $lesson])->with('status', 'Đã tạo bài học. Thêm video và tài liệu bên dưới.');
    }

    /** AI tự tạo bài tập mới cho bài học theo số câu từng kỹ năng (lưu Nháp). */
    public function aiCompose(Request $request, Course $course, CourseLesson $lesson): RedirectResponse
    {
        $this->lessonOf($course, $lesson);
        $rules = collect(AiImportService::COMPOSE_SKILLS)->mapWithKeys(fn ($l, $k) => ["counts.{$k}" => ['nullable', 'integer', 'min:0', 'max:30']])->all();
        $counts = array_map('intval', (array) ($request->validate(['counts' => ['required', 'array']] + $rules)['counts'] ?? []));
        $total = array_sum($counts);
        if ($total < 1 || $total > 60) {
            return back()->withErrors(['counts' => 'Chọn tổng từ 1 đến 60 câu hỏi lớn.']);
        }
        if (! filled(config('ai.openai.key'))) {
            return back()->withErrors(['counts' => 'Chưa cấu hình AI (Quản trị hệ thống › AI).']);
        }
        if (AiImport::where('lesson_id', $lesson->id)->whereIn('status', AiImport::RUNNING)->exists()) {
            return back()->with('status', 'AI đang tạo bài tập cho bài này, chờ xong rồi tạo thêm.');
        }
        app(AiImportService::class)->composeForLesson($lesson, $counts, auth()->user());

        return back()->with('status', "AI đang tạo {$total} câu hỏi lớn mới cho bài này (chạy nền, xong sẽ hiện ở danh sách bài tập dưới dạng Nháp).");
    }

    /** Lưu danh sách từ vựng của bài (mỗi dòng: từ | pinyin / từ loại | nghĩa | ví dụ) — học viên ôn bằng thẻ. */
    public function saveVocabulary(Request $request, Course $course, CourseLesson $lesson): RedirectResponse
    {
        $this->lessonOf($course, $lesson);
        $text = (string) $request->validate(['vocabulary' => ['nullable', 'string', 'max:30000']])['vocabulary'];
        $words = LessonVocabulary::parse($text, $course->language === 'zh');
        $lesson->update(['vocabulary' => $words ?: null]);

        return redirect()->to(route($this->lessonRoutePrefix().'.edit', [$course, $lesson]).'#vocabulary')
            ->with('status', $words ? 'Đã lưu '.count($words).' từ vựng — học viên ôn được ở mục "Ôn từ vựng".' : 'Đã xoá danh sách từ vựng của bài.');
    }

    /** AI lập danh sách từ vựng từ nội dung bài (giáo viên xem lại rồi lưu). */
    public function aiVocabulary(Course $course, CourseLesson $lesson): RedirectResponse
    {
        $this->lessonOf($course, $lesson);
        if (! filled(config('ai.openai.key'))) {
            return back()->withErrors(['vocabulary' => 'Chưa cấu hình AI (Quản trị hệ thống › AI).']);
        }
        try {
            $words = app(LessonVocabulary::class)->generate($lesson->load('course'), auth()->user());
        } catch (\App\Support\Ai\AiException $e) {
            return back()->withErrors(['vocabulary' => 'AI chưa lập được danh sách: '.$e->getMessage()]);
        }
        if (! $words) {
            return back()->withErrors(['vocabulary' => 'AI không tìm thấy từ vựng — hãy ghi rõ phần TỪ VỰNG trong ghi chú bài học rồi thử lại.']);
        }
        $lesson->update(['vocabulary' => $words]);

        return redirect()->to(route($this->lessonRoutePrefix().'.edit', [$course, $lesson]).'#vocabulary')
            ->with('status', 'AI đã lập '.count($words).' từ vựng. Kiểm tra lại, sửa nếu cần rồi bấm Lưu.');
    }

    /** AI lập từ vựng cho mọi bài học chưa có danh sách (chạy nền, mỗi bài một việc). */
    public function aiVocabularyAll(Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);
        if (! filled(config('ai.openai.key'))) {
            return back()->withErrors(['lessons' => 'Chưa cấu hình AI (Quản trị hệ thống › AI).']);
        }
        $actor = auth()->user();
        $ids = $course->lessons()->whereNull('vocabulary')->pluck('id');
        foreach ($ids as $id) {
            \App\Models\AiJob::dispatch('lesson_vocabulary', ['lesson_id' => $id, 'actor_type' => $actor instanceof Teacher ? 'teacher' : 'user', 'actor_id' => $actor->id],
                'course-vocab:'.$course->id, 0, 'course-vocab:'.$course->id);
        }

        return back()->with('status', $ids->isEmpty() ? 'Mọi bài học đã có danh sách từ vựng.' : "AI đang lập từ vựng cho {$ids->count()} bài học (chạy nền, vài phút). Mở từng bài để xem và sửa.");
    }

    public function edit(Course $course, CourseLesson $lesson): View
    {
        $this->lessonOf($course, $lesson)->load('videos', 'materials', 'exercises.items');

        return view('lessons.editor.edit', [
            'layout' => $this->lessonLayout(), 'prefix' => $this->lessonRoutePrefix(), 'course' => $course, 'lesson' => $lesson,
            'attempts' => self::attemptCounts([$lesson->id])[$lesson->id] ?? 0,
            'bunny' => app(VideoGuard::class)->bunnyEnabled(),
            'maxMb' => (int) config('video.max_upload_mb'),
            'customPrefix' => str_replace('lessons', 'custom', $this->lessonRoutePrefix()),   // admin.courses.custom | teacher.custom
            'generating' => AiImport::where('lesson_id', $lesson->id)->whereIn('status', AiImport::RUNNING)->exists(),
            // Bài AI dựng chưa thành bài tập (đang dựng / chờ duyệt / lỗi) — giáo viên chỉ thấy bản của mình.
            'pendingImports' => AiImport::where('lesson_id', $lesson->id)->whereNotIn('status', ['saved'])
                ->when(auth()->user() instanceof Teacher, fn ($q) => $q->where('created_by_type', 'teacher')->where('created_by_id', auth()->id()))
                ->latest('id')->get(),
        ]);
    }

    public function update(Request $request, Course $course, CourseLesson $lesson): RedirectResponse
    {
        $this->lessonOf($course, $lesson)->update($this->lessonData($request, $course));

        return back()->with('status', 'Đã lưu bài học.');
    }

    public function destroy(Request $request, Course $course, CourseLesson $lesson): RedirectResponse
    {
        $this->lessonOf($course, $lesson);
        // Chống xoá nhầm: phải tick xác nhận trong hộp cảnh báo.
        if (! $request->boolean('confirm')) {
            return back()->withErrors(['lesson' => 'Chưa xác nhận xoá bài học.']);
        }
        foreach ($lesson->exercises()->pluck('id') as $id) {
            Storage::disk('local')->deleteDirectory('custom-exercises/'.$id);
        }
        Storage::disk('local')->deleteDirectory($lesson->storageDir());
        $lesson->delete();

        return redirect()->route($this->lessonRoutePrefix().'.index', $course)->with('status', 'Đã xoá bài học.');
    }

    /** order[] = id bài học theo thứ tự mới. */
    public function reorder(Request $request, Course $course): RedirectResponse|JsonResponse
    {
        $this->authorizeCourse($course);
        $ids = array_map('intval', (array) $request->input('order', []));
        foreach ($ids as $i => $id) {
            CourseLesson::where('course_id', $course->id)->whereKey($id)->update(['position' => $i + 1]);
        }

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', 'Đã sắp xếp bài học.');
    }

    private function lessonData(Request $request, Course $course): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string', 'max:60000'],
            'require_video' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $data['require_video'] = $request->boolean('require_video');
        $data['is_published'] = $request->has('is_published') ? $request->boolean('is_published') : true;

        return $data;
    }

    // ---------------------------------------------------------------- video

    /** Nhận 1 mảnh video MP4 hoặc gói HLS (.zip) — ?upload=&part=&total=&name=&kind=local|hls */
    public function uploadVideo(Request $request, Course $course, CourseLesson $lesson, ChunkedUpload $upload): JsonResponse
    {
        $this->lessonOf($course, $lesson);
        $name = basename((string) $request->query('name'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $kind = $request->query('kind') === 'hls' ? 'hls' : 'local';
        if (($kind === 'local' && ! in_array($ext, self::VIDEO_EXT, true)) || ($kind === 'hls' && $ext !== 'zip')) {
            return response()->json(['ok' => false, 'message' => $kind === 'hls' ? 'Gói HLS phải là file .zip.' : 'Chỉ nhận video: '.implode(', ', self::VIDEO_EXT)], 422);
        }
        $uploadId = (string) $request->query('upload');
        if (! preg_match('/^[a-z0-9]{8,40}$/', $uploadId)) {
            return response()->json(['ok' => false, 'message' => 'Mã tải lên không hợp lệ.'], 422);
        }
        $key = "video-{$lesson->id}-{$uploadId}";
        try {
            $path = $upload->receive($request, $key, (int) $request->query('part'), (int) $request->query('total'), (int) config('video.max_upload_mb') * 1048576);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        if ($path === null) {
            return response()->json(['ok' => true, 'complete' => false]);
        }

        $title = trim((string) $request->query('title')) ?: pathinfo($name, PATHINFO_FILENAME);
        $video = $lesson->videos()->create(['title' => mb_substr($title, 0, 200), 'provider' => $kind, 'size' => filesize($path),
            'position' => (int) $lesson->videos()->max('position') + 1, 'duration_seconds' => $request->integer('duration') ?: null]);
        try {
            if ($kind === 'hls') {
                $this->extractHls($path, $lesson->storageDir()."/videos/{$video->id}", $video);
            } else {
                $dest = $lesson->storageDir()."/videos/{$video->id}.{$ext}";
                Storage::disk('local')->makeDirectory(dirname($dest));
                File::move($path, Storage::disk('local')->path($dest));
                $video->update(['path' => $dest]);
            }
        } catch (InvalidArgumentException $e) {
            $video->delete();
            ChunkedUpload::cleanup($key);

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        ChunkedUpload::cleanup($key);

        return response()->json(['ok' => true, 'complete' => true, 'id' => $video->id]);
    }

    /** Giải nén gói HLS: chỉ nhận file phẳng .m3u8/.ts/.m4s/.key…, phải có playlist. */
    private function extractHls(string $zipPath, string $dir, LessonVideo $video): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new InvalidArgumentException('Không mở được file zip.');
        }
        $abs = Storage::disk('local')->path($dir);
        File::ensureDirectoryExists($abs);
        $playlist = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (str_ends_with($entry, '/')) {
                continue;
            }
            $base = basename($entry);
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if (! in_array($ext, self::HLS_EXT, true) || ! preg_match('/^[A-Za-z0-9._-]+$/', $base)) {
                continue;
            }
            file_put_contents($abs.'/'.$base, $zip->getFromIndex($i));
            if ($ext === 'm3u8' && ($playlist === null || in_array($base, ['master.m3u8', 'index.m3u8', 'playlist.m3u8'], true))) {
                $playlist = $base;
            }
        }
        $zip->close();
        if (! $playlist) {
            File::deleteDirectory($abs);
            throw new InvalidArgumentException('Gói zip không có file playlist .m3u8.');
        }
        $video->update(['path' => $dir.'/'.$playlist]);
    }

    public function addBunny(Request $request, Course $course, CourseLesson $lesson): RedirectResponse
    {
        $this->lessonOf($course, $lesson);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'external_id' => ['required', 'string', 'regex:/^[0-9a-f-]{20,64}$/i'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
        ], ['external_id.regex' => 'Mã video Bunny (GUID) không hợp lệ.']);
        $lesson->videos()->create($data + ['provider' => 'bunny', 'position' => (int) $lesson->videos()->max('position') + 1]);

        return back()->with('status', 'Đã thêm video Bunny Stream.');
    }

    public function updateVideo(Request $request, Course $course, CourseLesson $lesson, LessonVideo $video): RedirectResponse
    {
        $this->lessonOf($course, $lesson);
        abort_unless($video->lesson_id === $lesson->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'position' => ['nullable', 'integer', 'min:0'],
            'captions' => ['nullable', 'file', 'max:2048'],
        ]);
        if ($request->hasFile('captions')) {
            $text = (string) file_get_contents($request->file('captions')->getRealPath());
            if (! str_starts_with(ltrim($text, "\xEF\xBB\xBF \n"), 'WEBVTT')) {
                // Đổi SRT → VTT (dấu phẩy mili giây → dấu chấm).
                $text = "WEBVTT\n\n".preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/', '$1.$2', $text);
            }
            $path = $lesson->storageDir()."/videos/{$video->id}.vtt";
            Storage::disk('local')->put($path, $text);
            $data['captions_path'] = $path;
        }
        unset($data['captions']);
        $video->update(array_filter($data, fn ($v) => $v !== null));

        return back()->with('status', 'Đã lưu video.');
    }

    public function destroyVideo(Course $course, CourseLesson $lesson, LessonVideo $video): RedirectResponse
    {
        $this->lessonOf($course, $lesson);
        abort_unless($video->lesson_id === $lesson->id, 404);
        $disk = Storage::disk('local');
        if ($video->provider === 'hls' && $video->path) {
            $disk->deleteDirectory(dirname($video->path));
        } elseif ($video->path) {
            $disk->delete($video->path);
        }
        if ($video->captions_path) {
            $disk->delete($video->captions_path);
        }
        $video->delete();

        return back()->with('status', 'Đã xoá video.');
    }

    /** Người soạn xem lại video MP4 (phát theo đoạn). */
    public function previewVideo(Course $course, CourseLesson $lesson, LessonVideo $video): BinaryFileResponse
    {
        $this->lessonOf($course, $lesson);
        abort_unless($video->lesson_id === $lesson->id && $video->provider === 'local' && $video->path, 404);

        return VideoResponse::file(Storage::disk('local')->path($video->path));
    }

    // ---------------------------------------------------------------- tài liệu

    public function uploadMaterial(Request $request, Course $course, CourseLesson $lesson): RedirectResponse
    {
        $this->lessonOf($course, $lesson);
        $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'file' => ['required', 'file', 'max:7900', 'extensions:'.implode(',', LessonMaterial::EXTENSIONS)],
        ]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs($lesson->storageDir().'/materials', Str::random(20).'.'.$ext, 'local');
        $lesson->materials()->create([
            'title' => $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize(),
            'position' => (int) $lesson->materials()->max('position') + 1,
        ]);

        return back()->with('status', 'Đã thêm tài liệu.');
    }

    public function destroyMaterial(Course $course, CourseLesson $lesson, LessonMaterial $material): RedirectResponse
    {
        $this->lessonOf($course, $lesson);
        abort_unless($material->lesson_id === $lesson->id, 404);
        Storage::disk('local')->delete($material->path);
        $material->delete();

        return back()->with('status', 'Đã xoá tài liệu.');
    }
}
