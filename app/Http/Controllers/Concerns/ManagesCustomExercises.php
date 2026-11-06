<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Course;
use App\Models\CustomExercise;
use App\Models\CustomExerciseItem;
use App\Support\CustomExercise\CustomExerciseService;
use App\Support\QuestionTypes\Registry;
use App\Support\QuestionTypes\Scoring;
use App\Support\Video\VideoResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Thư viện bài tập tuỳ chỉnh của khoá (tab "Bài tập tuỳ chỉnh"): tìm / lọc, tạo, soạn từng phần theo dạng bài của thư viện,
 * xem trước như học viên, nhân bản, thêm vào lớp. Dùng chung cho trang quản trị và cổng giáo viên.
 */
trait ManagesCustomExercises
{
    abstract protected function customLayout(): string;

    abstract protected function customRoutePrefix(): string;

    /** Người đang thao tác (User hoặc Teacher). */
    abstract protected function actor(): object;

    /** Được vào thư viện của khoá (xem, tạo, nhân bản)? */
    abstract protected function canUseLibrary(Course $course): bool;

    /** Được sửa / xoá / đổi trạng thái bài này? */
    abstract protected function canManage(CustomExercise $exercise): bool;

    /** Lớp của khoá mà người này được thêm bài vào. */
    abstract protected function assignableClassrooms(Course $course): Collection;

    abstract protected function isAdminSide(): bool;

    private function library(Course $course): void
    {
        abort_unless($this->canUseLibrary($course), 403, 'Bạn chưa có quyền dùng thư viện bài tập của khoá này.');
    }

    private function exerciseOf(Course $course, CustomExercise $exercise, bool $manage = false): CustomExercise
    {
        $this->library($course);
        abort_unless($exercise->course_id === $course->id, 404);
        abort_if(! $this->isAdminSide() && $exercise->status === 'hidden' && ! $this->canManage($exercise), 404);
        abort_if($manage && ! $this->canManage($exercise), 403, 'Chỉ người tạo, người được sửa khoá hoặc quản trị viên mới sửa được bài này. Hãy nhân bản để sửa riêng.');

        return $exercise;
    }

    private function viewData(Course $course, array $extra = []): array
    {
        return $extra + ['layout' => $this->customLayout(), 'prefix' => $this->customRoutePrefix(), 'course' => $course, 'adminSide' => $this->isAdminSide()];
    }

    /** Bài tập nằm trong bài học: danh sách bài tập xem ở trang bài học của khoá. */
    public function index(Request $request, Course $course): RedirectResponse
    {
        $this->library($course);
        $lessonsRoute = str_replace('custom', 'lessons', $this->customRoutePrefix()).'.index';

        return $this->isAdminSide() || auth()->user()->canEditCourse($course)
            ? redirect()->route($lessonsRoute, $course)
            : redirect()->route('teacher.classrooms.index');
    }

    /**
     * Tạo bài tập trong một bài học. Bài chung của khoá: quản trị viên / giáo viên được sửa khoá.
     * Bài riêng của lớp (source_classroom_id): giáo viên có quyền "tuỳ chỉnh bài tập" ở lớp đó.
     */
    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->library($course);
        $data = $this->metaData($request, $course);
        $classroomId = $request->integer('source_classroom_id') ?: null;
        if ($classroomId) {
            abort_unless($this->assignableClassrooms($course)->contains('id', $classroomId), 403);
        } else {
            abort_unless($this->isAdminSide() || auth()->user()->canEditCourse($course), 403, 'Bài tập chung của khoá cần quyền sửa khoá. Bạn chỉ tạo được bài tập riêng cho lớp của mình.');
        }
        $ex = CustomExercise::create($data + ['course_id' => $course->id, 'source_classroom_id' => $classroomId, 'status' => 'draft']
            + (new CustomExercise)->createdBy($this->actor()));

        return redirect()->route($this->customRoutePrefix().'.edit', [$course, $ex])->with('status', 'Đã tạo bài tập trong bài “'.$ex->lesson->title.'”. Thêm các phần bên dưới rồi chuyển trạng thái sang "Dùng được".');
    }

    public function edit(Course $course, CustomExercise $exercise): View
    {
        $this->exerciseOf($course, $exercise)->load('items');

        return view('custom.library.edit', $this->viewData($course, [
            'exercise' => $exercise, 'types' => app(CustomExerciseService::class)->typesFor($course),
            'canManage' => $this->canManage($exercise), 'locked' => $exercise->locked(), 'attemptCount' => $exercise->submittedAttemptCount(),
            'classrooms' => $this->assignableClassrooms($course), 'lessons' => $course->lessons()->get(['id', 'title']),
            'scales' => Scoring::SCALES,
        ]));
    }

    public function update(Request $request, Course $course, CustomExercise $exercise): RedirectResponse
    {
        $this->exerciseOf($course, $exercise, true);
        $data = $this->metaData($request, $course);
        if ($request->filled('status')) {
            $data['status'] = $request->validate(['status' => [Rule::in(array_keys(CustomExercise::STATUSES))]])['status'];
            if ($data['status'] === 'published' && $exercise->items()->count() === 0) {
                return back()->withErrors(['status' => 'Bài chưa có phần nào.']);
            }
        }
        if ($this->isAdminSide()) {
            $data['is_standard'] = $request->boolean('is_standard');
        }
        $exercise->update($data);

        return back()->with('status', 'Đã lưu bài tập.');
    }

    /**
     * Chấm lại mọi bài đã nộp theo nội dung / đáp án hiện tại (sau khi sửa đáp án sai). Phần Viết / Nói đã được AI hoặc giáo viên chấm
     * giữ nguyên điểm; phần mới thêm không tính cho lượt cũ; phần đã xoá bị bỏ khỏi điểm.
     */
    public function regrade(Course $course, CustomExercise $exercise): RedirectResponse
    {
        $this->exerciseOf($course, $exercise, true);
        $n = app(CustomExerciseService::class)->regradeExercise($exercise);

        return back()->with('status', "Đã chấm lại {$n} bài làm theo đáp án mới.");
    }

    /** Đổi nhanh trạng thái từ danh sách bài tập của bài học: Nháp / Dùng được (xuất bản) / Ẩn. */
    public function quickStatus(Request $request, Course $course, CustomExercise $exercise): RedirectResponse
    {
        $this->exerciseOf($course, $exercise, true);
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(CustomExercise::STATUSES))]])['status'];
        if ($status === 'published' && $exercise->items()->count() === 0) {
            return back()->withErrors(['status' => 'Bài “'.$exercise->title.'” chưa có phần nào nên chưa xuất bản được.']);
        }
        $exercise->update(['status' => $status]);

        return back()->with('status', '“'.$exercise->title.'”: '.CustomExercise::STATUSES[$status].'.');
    }

    public function destroy(Course $course, CustomExercise $exercise): RedirectResponse
    {
        $this->exerciseOf($course, $exercise, true);
        if ($exercise->locked()) {
            $exercise->update(['status' => 'hidden']);

            return redirect()->route(str_replace('custom', 'lessons', $this->customRoutePrefix()).'.edit', [$course, $exercise->lesson_id])->with('status', 'Bài đã có học viên làm nên chỉ được ẩn (kết quả vẫn giữ).');
        }
        $lessonId = $exercise->lesson_id;
        Storage::disk('local')->deleteDirectory('custom-exercises/'.$exercise->id);
        $exercise->delete();

        return $this->isAdminSide() || auth()->user()->canEditCourse($course)
            ? redirect()->route(str_replace('custom', 'lessons', $this->customRoutePrefix()).'.edit', [$course, $lessonId])->with('status', 'Đã xoá bài tập.')
            : redirect()->route('teacher.classrooms.index')->with('status', 'Đã xoá bài tập.');
    }

    public function duplicate(Request $request, Course $course, CustomExercise $exercise): RedirectResponse
    {
        $this->exerciseOf($course, $exercise);
        $copy = $exercise->load('items')->duplicate($this->actor(), $request->integer('classroom_id') ?: null);

        return redirect()->route($this->customRoutePrefix().'.edit', [$course, $copy])->with('status', 'Đã nhân bản. Bản sao là của bạn — sửa thoải mái, không ảnh hưởng bản gốc.');
    }

    public function preview(Course $course, CustomExercise $exercise): View
    {
        $this->exerciseOf($course, $exercise)->load('items');

        return view('custom.library.preview', $this->viewData($course, ['exercise' => $exercise]));
    }

    // ---------------------------------------------------------------- các phần

    public function itemStore(Request $request, Course $course, CustomExercise $exercise): RedirectResponse
    {
        $this->exerciseOf($course, $exercise, true);
        $types = app(CustomExerciseService::class)->typesFor($course);
        $data = $request->validate(['type_code' => ['required', Rule::in($types->keys()->all())], 'title' => ['nullable', 'string', 'max:200']]);
        $item = $exercise->items()->create(['type_code' => $data['type_code'], 'title' => $data['title'] ?? null,
            'content' => Registry::get($data['type_code'])->sample, 'position' => (int) $exercise->items()->max('position') + 1]);

        return redirect()->to(route($this->customRoutePrefix().'.edit', [$course, $exercise]).'#item-'.$item->id)
            ->with('status', 'Đã thêm phần với nội dung mẫu — sửa JSON nội dung rồi bấm Lưu.');
    }

    public function itemUpdate(Request $request, Course $course, CustomExercise $exercise, CustomExerciseItem $item): RedirectResponse
    {
        $this->exerciseOf($course, $exercise, true);
        abort_unless($item->custom_exercise_id === $exercise->id, 404);
        $request->validate([
            'title' => ['nullable', 'string', 'max:200'], 'content' => ['required', 'string', 'max:200000'],
            'points' => ['nullable', 'numeric', 'min:0.05', 'max:100'], 'penalty' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scale' => ['nullable', Rule::in(array_keys(Scoring::SCALES))], 'partial' => ['nullable', 'in:0,1'],
        ]);
        $content = json_decode($request->input('content'), true);
        if ($errors = app(CustomExerciseService::class)->validateItem($item->type_code, $content)) {
            return back()->withInput()->withErrors(['item_'.$item->id => array_slice($errors, 0, 8)])->withFragment('item-'.$item->id);
        }
        $scoring = array_filter([
            'points' => $request->filled('points') ? (float) $request->points : null,
            'penalty' => $request->filled('penalty') ? (float) $request->penalty : null,
            'scale' => $request->input('scale') ?: null,
            'partial' => $request->filled('partial') ? (bool) $request->partial : null,
        ], fn ($v) => $v !== null);
        $item->update(['title' => $request->input('title'), 'content' => $content, 'scoring' => $scoring ?: null]);

        return redirect()->to(route($this->customRoutePrefix().'.edit', [$course, $exercise]).'#item-'.$item->id)->with('status', 'Đã lưu phần.');
    }

    public function itemDestroy(Course $course, CustomExercise $exercise, CustomExerciseItem $item): RedirectResponse
    {
        $this->exerciseOf($course, $exercise, true);
        abort_unless($item->custom_exercise_id === $exercise->id, 404);
        if ($item->audio_path) {
            Storage::disk('local')->delete($item->audio_path);
        }
        $item->delete();

        return back()->with('status', 'Đã xoá phần.');
    }

    public function itemMove(Request $request, Course $course, CustomExercise $exercise, CustomExerciseItem $item): RedirectResponse
    {
        $this->exerciseOf($course, $exercise, true);
        $items = $exercise->items()->get()->values();
        $i = $items->search(fn ($x) => $x->id === $item->id);
        $j = $i + ($request->input('dir') === 'up' ? -1 : 1);
        if ($i !== false && isset($items[$j])) {
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
            $items->each(fn ($x, $k) => $x->update(['position' => $k + 1]));
        }

        return back();
    }

    public function itemAudio(Request $request, Course $course, CustomExercise $exercise, CustomExerciseItem $item): RedirectResponse
    {
        $this->exerciseOf($course, $exercise, true);
        abort_unless($item->custom_exercise_id === $exercise->id, 404);
        // Tải file mới / gỡ file: bỏ luôn trạng thái giọng AI và file gốc đang giữ.
        $meta = $item->meta ?? [];
        if (! empty($meta['audio_original'])) {
            Storage::disk('local')->delete($meta['audio_original']);
        }
        unset($meta['audio_ai'], $meta['audio_original']);
        $item->update(['meta' => $meta]);
        if ($request->boolean('remove')) {
            if ($item->audio_path) {
                Storage::disk('local')->delete($item->audio_path);
            }
            $item->update(['audio_path' => null]);

            return back()->with('status', 'Đã gỡ file nghe.');
        }
        $request->validate(['audio' => ['required', 'file', 'max:7900', 'extensions:mp3,m4a,wav,ogg,aac']]);
        $file = $request->file('audio');
        if ($item->audio_path) {
            Storage::disk('local')->delete($item->audio_path);
        }
        $path = $file->storeAs("custom-exercises/{$exercise->id}/audio", $item->id.'.'.strtolower($file->getClientOriginalExtension()), 'local');
        $item->update(['audio_path' => $path]);

        return redirect()->to(route($this->customRoutePrefix().'.edit', [$course, $exercise]).'#item-'.$item->id)->with('status', 'Đã gắn file nghe cho phần.');
    }

    /**
     * AI cho một phần của bài đã lưu: action = rebuild (style strict|model, note) | tts (giọng đọc AI cho file nghe) | revert (dùng lại file gốc).
     * Gọi ngầm từ trang sửa bài (JSON) — chỉ thẻ của phần đó chuyển sang "AI đang làm".
     */
    public function itemAi(Request $request, Course $course, CustomExercise $exercise, CustomExerciseItem $item): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->exerciseOf($course, $exercise, true);
        abort_unless($item->custom_exercise_id === $exercise->id, 404);
        $data = $request->validate(['action' => ['required', Rule::in(['rebuild', 'tts', 'revert'])], 'style' => ['nullable', Rule::in(['strict', 'model'])], 'note' => ['nullable', 'string', 'max:1000']]);
        $service = app(\App\Support\CustomExercise\ItemAiService::class);
        $error = null;
        if ($data['action'] !== 'revert' && ! filled(config('ai.openai.key'))) {
            $error = 'Chưa cấu hình AI (Quản trị hệ thống › AI).';
        } elseif ($data['action'] === 'tts' && ! config('ai.tts_enabled')) {
            $error = 'Giọng đọc AI đang tắt (bật trong Quản trị hệ thống › AI).';
        } elseif ($data['action'] === 'tts' && \App\Support\CustomExercise\ItemAiService::speechText($item) === '') {
            $error = 'Phần này chưa có lời bài nghe để đọc — thêm "Lời bài nghe" trong mục Sửa nội dung trước.';
        } elseif (($item->meta['ai']['status'] ?? null) === 'pending') {
            $error = 'AI đang xử lý phần này, chờ chút.';
        }
        if (! $error) {
            if ($data['action'] === 'revert') {
                $service->revertAudio($item);
            } else {
                $service->queue($item, $data['action'], ['style' => $data['style'] ?? 'strict', 'note' => $data['note'] ?? null], $this->actor());
            }
        }
        $message = $error ?? ['rebuild' => 'AI đang dựng lại phần này…', 'tts' => 'AI đang tạo giọng đọc cho phần này…', 'revert' => 'Đã dùng lại file nghe gốc.'][$data['action']];
        if ($request->expectsJson()) {
            return response()->json(['ok' => ! $error, 'message' => $message, 'pending' => ! $error && $data['action'] !== 'revert'], $error ? 422 : 200);
        }

        $back = redirect()->to(route($this->customRoutePrefix().'.edit', [$course, $exercise]).'#item-'.$item->id);

        return $error ? $back->withErrors(['item_'.$item->id => $error]) : $back->with('status', $message);
    }

    /** Trạng thái AI của các phần (và chạy hàng đợi của bài này — dự phòng khi hosting chưa cài cron). */
    public function itemAiStatus(Course $course, CustomExercise $exercise, \App\Support\Ai\Jobs\AiJobRunner $runner): \Illuminate\Http\JsonResponse
    {
        $this->exerciseOf($course, $exercise);
        $items = fn () => $exercise->items()->get(['id', 'meta']);
        if ($items()->contains(fn ($i) => ($i->meta['ai']['status'] ?? null) === 'pending')) {
            $runner->work(20, 2, null, 'citem:'.$exercise->id);
        }

        return response()->json(['items' => $items()->mapWithKeys(fn ($i) => [$i->id => $i->meta['ai'] ?? null])]);
    }

    public function itemAudioFile(Course $course, CustomExercise $exercise, CustomExerciseItem $item): BinaryFileResponse
    {
        $this->exerciseOf($course, $exercise);
        abort_unless($item->custom_exercise_id === $exercise->id && $item->audio_path, 404);

        return VideoResponse::file(Storage::disk('local')->path($item->audio_path));
    }

    public function itemImage(Request $request, Course $course, CustomExercise $exercise, CustomExerciseItem $item): RedirectResponse
    {
        $this->exerciseOf($course, $exercise, true);
        abort_unless($item->custom_exercise_id === $exercise->id, 404);
        if ($item->image_path) {
            Storage::disk('local')->delete($item->image_path);
        }
        if ($request->boolean('remove')) {
            $item->update(['image_path' => null]);

            return back()->with('status', 'Đã gỡ ảnh.');
        }
        $request->validate(['image' => ['required', 'image', 'max:7900']]);
        $path = $request->file('image')->storeAs("custom-exercises/{$exercise->id}/images", $item->id.'-'.time().'.'.$request->file('image')->extension(), 'local');
        $item->update(['image_path' => $path]);

        return redirect()->to(route($this->customRoutePrefix().'.edit', [$course, $exercise]).'#item-'.$item->id)->with('status', 'Đã gắn ảnh cho phần.');
    }

    public function itemImageFile(Course $course, CustomExercise $exercise, CustomExerciseItem $item): BinaryFileResponse
    {
        $this->exerciseOf($course, $exercise);
        abort_unless($item->custom_exercise_id === $exercise->id && $item->image_path, 404);

        return response()->file(Storage::disk('local')->path($item->image_path), ['Cache-Control' => 'private, max-age=3600']);
    }

    private function metaData(Request $request, Course $course): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'skill' => ['required', Rule::in(array_keys(CustomExercise::SKILLS))],
            'lesson_id' => ['required', 'integer', Rule::exists('course_lessons', 'id')->where('course_id', $course->id)],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'scale' => ['nullable', Rule::in(array_keys(Scoring::SCALES))],
        ]);
        $data['scoring'] = ! empty($data['scale']) ? ['scale' => $data['scale']] : null;
        unset($data['scale']);

        return $data;
    }
}
