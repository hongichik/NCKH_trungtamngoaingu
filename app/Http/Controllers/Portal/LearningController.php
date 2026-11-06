<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AiJob;
use App\Models\Assignment;
use App\Models\AudioTrack;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseSet;
use App\Models\LessonVideo;
use App\Models\TextbookUnit;
use App\Models\UnitTest;
use App\Models\VideoProgress;
use App\Support\Exercise\Scorer;
use App\Support\Exercise\UnitTestService;
use App\Support\Progress\Gamification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Khu học tập của học viên (cổng /thi-sinh, đăng nhập học viên).
 * Khoá học → Unit (bài học: từ vựng, ngữ pháp, bài đọc, hội thoại) → bộ bài tập Đọc / Nghe → nộp → chấm trên máy chủ.
 */
class LearningController extends Controller
{
    private function student()
    {
        return auth('student')->user();
    }

    /**
     * Học viên chỉ vào được nội dung của khoá học khi thuộc một lớp học của khoá đó.
     * $forWork = true (học, làm bài): lớp phải đang mở (đã bắt đầu, chưa kết thúc hoặc được gia hạn).
     * $forWork = false (xem kết quả cũ): chỉ cần thuộc lớp.
     */
    private function published(Course $course, bool $forWork = true): Course
    {
        $classes = $this->student()->classrooms()->where('course_id', $course->id)->get();
        abort_if($classes->isEmpty(), 404);
        if ($forWork && ! $classes->contains(fn ($c) => $c->accessOpen())) {
            abort(403, $classes->first()->closedReason() ?? 'Lớp không mở.');
        }

        return $course;
    }

    private function unitOf(Course $course, TextbookUnit $unit): TextbookUnit
    {
        abort_unless($unit->textbook_id === $course->textbook_id && ! $unit->skipped && $unit->status === 'ready', 404);

        return $unit;
    }

    private function setOf(Course $course, ExerciseSet $set): ExerciseSet
    {
        $set->loadMissing('unit');
        abort_unless($set->status === 'published', 404);
        $this->unitOf($course, $set->unit);

        return $set;
    }

    /** "Lớp học của tôi". */
    public function classes(): View
    {
        $student = $this->student();
        $classes = $student->classrooms()->with('teacher', 'course')->get()->each(function ($c) use ($student) {
            $c->todo = $c->assignments()->with(['tests' => fn ($q) => $q->where('student_id', $student->id)])->get()
                ->filter(fn ($a) => in_array($a->statusFor($student->id), ['todo', 'in_progress'], true))->count();
        });

        return view('portal.classes.index', ['classes' => $classes]);
    }

    /** 1 lớp: các bài được giao (làm bài, kết quả, lịch sử, xem từ vựng / ngữ pháp / bài đọc). */
    public function classroom(Classroom $classroom): View
    {
        $student = $this->student();
        abort_unless($classroom->students()->whereKey($student->id)->exists(), 404);
        $assignments = $classroom->assignments()
            ->where(fn ($q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', now()->addDays(30)))
            ->with(['unit.profile', 'tests' => fn ($q) => $q->where('student_id', $student->id)->latest('id')
                ->withExists(['attempts as teacher_reviewed' => fn ($a) => $a->whereNotNull('reviewed_at')->where(fn ($w) => $w->whereNotNull('teacher_score')->orWhereNotNull('teacher_comment'))])])
            ->get();

        $lessons = $classroom->course ? $classroom->course->lessons()->where('is_published', true)->withCount('videos')->get() : collect();
        $done = VideoProgress::where('student_id', $student->id)->whereNotNull('completed_at')->pluck('lesson_video_id')->all();
        $lessons->each(function ($l) use ($classroom, $done) {
            $s = $l->scheduleFor($classroom);
            $l->hidden_for_class = $s && $s->hidden;
            $l->opens_at_for_class = $s && $s->opens_at ? Carbon::parse($s->opens_at) : null;
            $l->open_now = $l->openFor($classroom);
            $l->videos_done = $l->videos_count ? LessonVideo::where('lesson_id', $l->id)->whereIn('id', $done)->count() : 0;
            $l->exercise_count = $l->exercisesFor($classroom)->count();
        });

        return view('portal.classes.show', ['classroom' => $classroom->load('teacher', 'course'), 'assignments' => $assignments, 'studentId' => $student->id,
            'lessons' => $lessons->reject(fn ($l) => $l->hidden_for_class)->values(),

            'board' => $classroom->show_leaderboard ? app(Gamification::class)->leaderboard($classroom) : null]);
    }

    /** Link mời vào lớp: xác nhận tham gia. */
    public function invite(string $token): View|RedirectResponse
    {
        $classroom = Classroom::where('invite_token', $token)->with('teacher', 'course')->first();
        if ($classroom && $classroom->students()->whereKey($this->student()->id)->exists()) {
            return redirect()->route('portal.classes.show', $classroom);
        }

        return view('portal.classes.invite', ['classroom' => $classroom && $classroom->allow_join ? $classroom : null, 'token' => $token]);
    }

    public function acceptInvite(string $token): RedirectResponse
    {
        $classroom = Classroom::where('invite_token', $token)->first();
        abort_unless($classroom && $classroom->allow_join, 404);
        $classroom->students()->syncWithoutDetaching([$this->student()->id]);

        return redirect()->route('portal.classes.show', $classroom)->with('status', 'Đã vào lớp “'.$classroom->name.'”.');
    }

    public function startAssignment(Assignment $assignment, UnitTestService $tests): RedirectResponse
    {
        [$test, $error] = $tests->startAssignment($assignment->load('classroom'), $this->student());
        if (! $test) {
            return back()->with('status_error', $error);
        }

        return redirect()->route('portal.learning.test', [$test->course_id, $test]);
    }

    /** Xem từ vựng / ngữ pháp / bài đọc của Unit (bài đọc chỉ khi giáo viên cho phép). */
    public function material(Course $course, TextbookUnit $unit, string $kind): View
    {
        $this->published($course);
        $this->unitOf($course, $unit);
        abort_unless(in_array($kind, ['tu-vung', 'ngu-phap', 'bai-doc'], true), 404);
        abort_if($kind === 'bai-doc' && ! $course->show_reading_preview, 404);

        $classroom = $this->student()->classrooms()->where('course_id', $course->id)->find(request()->integer('lop'));

        return view('portal.learning.material', ['course' => $course, 'unit' => $unit, 'kind' => $kind, 'profile' => $unit->profile?->data ?? [], 'zh' => $course->language === 'zh', 'classroom' => $classroom]);
    }

    /**
     * Trang làm bài: cả 4 phần trên 1 trang (chuyển tab tự do), nộp 1 lần ở cuối.
     */
    public function test(Course $course, UnitTest $test): View|RedirectResponse
    {
        $this->published($course);
        abort_unless($test->student_id === $this->student()->id && $test->course_id === $course->id, 404);
        if ($test->status !== 'in_progress') {
            return redirect()->route('portal.learning.test-result', [$course, $test]);
        }

        $sections = [];
        foreach ($test->sections as $sec) {
            $set = ExerciseSet::with('questions', 'unit')->find($sec['set_id']);
            if ($set) {
                $sections[] = $this->sectionData($course, $set);
            }
        }
        abort_unless($sections, 404);

        if ($test->deadline_at && app(UnitTestService::class)->isLate($test)) {
            // Hết giờ mà chưa nộp (vd đóng trình duyệt) → nộp tự động bài trống
            app(UnitTestService::class)->submitAll($test, $this->emptyAttempts($test));

            return redirect()->route('portal.learning.test-result', [$course, $test])->with('status_error', 'Đã hết giờ làm bài.');
        }

        return view('portal.learning.test', [
            'course' => $course,
            'test' => $test->load('unit', 'assignment.classroom'),
            'sections' => $sections,
            'speechLang' => $course->language === 'zh' ? 'zh-CN' : 'en-US',
        ]);
    }

    /** Nộp cả bài (mọi phần cùng lúc). */
    public function submitTest(Request $request, Course $course, UnitTest $test, Scorer $scorer, UnitTestService $tests): RedirectResponse
    {
        $this->published($course);
        abort_unless($test->student_id === $this->student()->id && $test->course_id === $course->id, 404);
        if ($test->status !== 'in_progress') {
            return redirect()->route('portal.learning.test-result', [$course, $test]);
        }

        $all = (array) $request->input('answers', []);
        if ($tests->isLate($test)) {
            $all = []; // nộp quá giờ (đã tính thời gian châm chước) → không nhận bài làm
        }
        $attempts = [];
        foreach ($test->sections as $i => $sec) {
            $set = ExerciseSet::with('questions', 'unit')->find($sec['set_id']);
            if ($set) {
                $attempts[$i] = $this->createAttempt($set, (array) ($all[$set->id] ?? []), $scorer, $test);
            }
        }
        $tests->submitAll($test, $attempts);

        return redirect()->route('portal.learning.test-result', [$course, $test]);
    }

    public function testResult(Course $course, UnitTest $test): View
    {
        $this->published($course, false);
        abort_unless($test->student_id === $this->student()->id && $test->course_id === $course->id, 404);
        $attempts = ExerciseAttempt::whereIn('id', array_filter(array_column($test->sections, 'attempt_id')))->get()->keyBy('id');

        $test->load('unit', 'assignment.classroom');

        return view('portal.learning.test-result', [
            'course' => $course, 'test' => $test, 'attempts' => $attempts,
            'answersVisible' => ! $test->assignment || $test->assignment->answersVisible(),
        ]);
    }

    public function testStatus(Course $course, UnitTest $test): JsonResponse
    {
        abort_unless($test->student_id === $this->student()->id, 404);

        return response()->json(['status' => $test->status]);
    }

    /** @return array<int, ExerciseAttempt> */
    private function emptyAttempts(UnitTest $test): array
    {
        $out = [];
        foreach ($test->sections as $i => $sec) {
            if ($set = ExerciseSet::with('questions', 'unit')->find($sec['set_id'])) {
                $out[$i] = $this->createAttempt($set, [], app(Scorer::class), $test);
            }
        }

        return $out;
    }

    /** Luyện 1 bộ bài tập lẻ (không thuộc bài làm 4 kỹ năng). */
    public function exercise(Course $course, ExerciseSet $set): View
    {
        $this->published($course);
        $this->setOf($course, $set);

        return view('portal.learning.exercise', $this->sectionData($course, $set) + [
            'course' => $course,
            'unit' => $set->unit,
            'speechLang' => $course->language === 'zh' ? 'zh-CN' : 'en-US',
        ]);
    }

    /**
     * Dữ liệu hiển thị 1 phần cho học viên — CHỈ dữ liệu an toàn: không có đáp án, căn cứ, giải thích,
     * bài mẫu, lời thoại (trừ chế độ giọng trình duyệt cần chữ để đọc).
     */
    private function sectionData(Course $course, ExerciseSet $set): array
    {
        $this->setOf($course, $set);
        $d = $set->data ?? [];
        $audio = null;
        if ($set->skill === 'listening') {
            $mode = $set->audio_mode;
            if ($mode === 'tts' && empty($d['tts'])) {
                $mode = 'browser';
            }
            $audio = match ($mode) {
                'textbook' => ['mode' => 'textbook', 'srcs' => array_map(fn ($i) => route('portal.learning.audio', [$course, $set, 'i' => $i]), array_keys($d['track_ids'] ?? [0]))],
                'tts' => ['mode' => 'tts', 'files' => array_map(fn ($p) => '/storage/'.ltrim($p, '/'), $d['tts'])],
                default => [
                    'mode' => 'browser',
                    'lang' => $course->language === 'zh' ? 'zh-CN' : 'en-US',
                    'lines' => array_map(fn ($l) => ['voice' => collect($d['speakers'] ?? [])->firstWhere('name', $l['speaker'])['voice'] ?? 'female', 'text' => $l['text']], $d['script'] ?? []),
                ],
            };
        }

        return [
            'set' => $set,
            'passage' => $set->skill === 'reading' ? ($d['passage'] ?? '') : null,
            'cloze' => $set->skill === 'reading' ? ($d['cloze_passage'] ?? '') : null,
            'questions' => $set->questions->map->forStudent()->all(),
            'audio' => $audio,
            'recorded' => collect(session("rec.{$set->id}", []))->keys()->all(),
        ];
    }

    /** Nộp 1 bộ bài tập lẻ (luyện tập). */
    public function submit(Request $request, Course $course, ExerciseSet $set, Scorer $scorer): RedirectResponse
    {
        $this->published($course);
        $this->setOf($course, $set);
        $attempt = $this->createAttempt($set->load('questions'), (array) $request->input('answers', []), $scorer);

        return redirect()->route('portal.learning.result', [$course, $attempt]);
    }

    /**
     * Tạo lượt làm cho 1 bộ: Đọc / Nghe chấm ngay; Nói / Viết lưu bài + ghi âm rồi chấm nền bằng AI.
     */
    private function createAttempt(ExerciseSet $set, array $raw, Scorer $scorer, ?UnitTest $test = null): ExerciseAttempt
    {
        $answers = collect($raw)
            ->map(fn ($v) => mb_substr(is_array($v) ? implode('-', $v) : (string) $v, 0, 5000))
            ->only($set->questions->pluck('code'))
            ->all();
        $base = ['exercise_set_id' => $set->id, 'student_id' => $this->student()->id, 'unit_test_id' => $test?->id, 'answers' => $answers];

        if (! $set->gradedByAi()) {
            $r = $scorer->score($set, $answers);

            return ExerciseAttempt::create($base + ['result' => $r['items'], 'correct' => $r['correct'], 'total' => $r['total'], 'score' => $r['score']]);
        }

        $attempt = ExerciseAttempt::create($base + ['files' => [], 'result' => [], 'correct' => 0, 'total' => $set->questions->count(), 'score' => 0, 'status' => 'grading']);

        // Ghi âm đã tải lên trước đó (theo từng nhiệm vụ) → chuyển sang thư mục của lượt làm
        $files = [];
        foreach ($set->questions as $q) {
            $tmp = $this->recordingTmp($set, $q->code);
            $meta = session("rec.{$set->id}.{$q->code}");
            if ($meta && Storage::disk('local')->exists($tmp.'.'.$meta['ext'])) {
                $dest = "recordings/attempt-{$attempt->id}/{$q->code}.{$meta['ext']}";
                Storage::disk('local')->move($tmp.'.'.$meta['ext'], $dest);
                $files[$q->code] = ['path' => $dest, 'duration' => $meta['duration']];
            }
            session()->forget("rec.{$set->id}.{$q->code}");
        }
        $attempt->update(['files' => $files]);
        AiJob::dispatch('exercise_grade', ['attempt_id' => $attempt->id], 'attempt:'.$attempt->id, 0, 'student:'.$this->student()->id);

        return $attempt;
    }

    public function result(Course $course, ExerciseAttempt $attempt): View
    {
        $this->published($course, false);
        abort_unless($attempt->student_id === $this->student()->id, 404);
        $assignment = $attempt->unitTest?->assignment;
        abort_if($assignment && ! $assignment->answersVisible(), 403, 'Đáp án sẽ hiện sau hạn nộp bài.');
        $set = $this->setOf($course, $attempt->set);
        $d = $set->data ?? [];

        return view('portal.learning.result', [
            'course' => $course,
            'set' => $set,
            'attempt' => $attempt,
            'passage' => $d['passage'] ?? '',
            'cloze' => $d['cloze_passage'] ?? '',
            'script' => $d['script'] ?? [],
            'tts' => array_map(fn ($p) => '/storage/'.ltrim($p, '/'), $d['tts'] ?? []),
            'audioSrcs' => $set->audio_mode === 'textbook' ? array_map(fn ($i) => route('portal.learning.audio', [$course, $set, 'i' => $i]), array_keys($d['track_ids'] ?? [0])) : [],
            'history' => ExerciseAttempt::where('student_id', $this->student()->id)->where('exercise_set_id', $set->id)->latest('id')->limit(10)->get(['id', 'score', 'created_at']),
        ]);
    }

    /** Học viên tải lên ghi âm của 1 nhiệm vụ Nói (trước khi nộp cả bài). */
    public function record(Request $request, Course $course, ExerciseSet $set, string $code): JsonResponse
    {
        $this->published($course);
        $this->setOf($course, $set);
        abort_unless($set->skill === 'speaking' && $set->questions()->where('code', $code)->exists(), 404);
        $data = $request->validate([
            'audio' => ['required', 'file', 'max:5120'],
            'duration' => ['required', 'numeric', 'min:0.5', 'max:150'],
        ], ['audio.max' => 'Ghi âm tối đa 5 MB (khoảng 2 phút).']);

        $mime = strtolower(explode(';', (string) $request->file('audio')->getMimeType())[0]);
        $ext = match (true) {
            str_contains($mime, 'mp4') || str_contains($mime, 'm4a') || str_contains($mime, 'aac') => 'm4a',
            str_contains($mime, 'ogg') => 'ogg',
            str_contains($mime, 'wav') => 'wav',
            str_contains($mime, 'mpeg') => 'mp3',
            str_contains($mime, 'webm') || str_contains($mime, 'video') || $mime === 'application/octet-stream' => 'webm',
            default => null,
        };
        if (! $ext) {
            return response()->json(['ok' => false, 'message' => 'Định dạng ghi âm không hỗ trợ.'], 422);
        }

        $tmp = $this->recordingTmp($set, $code);
        foreach (['webm', 'm4a', 'ogg', 'wav', 'mp3'] as $e) {
            Storage::disk('local')->delete($tmp.'.'.$e);
        }
        Storage::disk('local')->putFileAs(dirname($tmp), $request->file('audio'), basename($tmp).'.'.$ext);
        session(["rec.{$set->id}.{$code}" => ['ext' => $ext, 'duration' => (float) $data['duration']]]);

        return response()->json(['ok' => true]);
    }

    private function recordingTmp(ExerciseSet $set, string $code): string
    {
        return 'recordings/tmp/student-'.$this->student()->id."/set-{$set->id}/".preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
    }

    /** Trang kết quả hỏi đã chấm xong chưa (bài Nói / Viết). */
    public function resultStatus(Course $course, ExerciseAttempt $attempt): JsonResponse
    {
        abort_unless($attempt->student_id === $this->student()->id, 404);

        return response()->json(['status' => $attempt->status]);
    }

    /** Audio gốc của giáo trình cho bài Nghe (chỉ học viên đăng nhập, bộ bài đã xuất bản). */
    public function audio(Request $request, Course $course, ExerciseSet $set): BinaryFileResponse
    {
        $this->published($course);
        $this->setOf($course, $set);
        abort_unless($set->audio_mode === 'textbook', 404);
        $ids = $set->data['track_ids'] ?? array_filter([$set->audio_track_id]);
        $track = AudioTrack::where('textbook_id', $set->unit->textbook_id)->find($ids[(int) $request->query('i', 0)] ?? 0);
        abort_unless($track, 404);

        return response()->file(Storage::disk('local')->path($track->file_path));
    }

    /** @return array<int, float> set_id => điểm cao nhất của học viên */
    private function bestScores(array $setIds): array
    {
        return ExerciseAttempt::where('student_id', $this->student()->id)->whereIn('exercise_set_id', $setIds ?: [0])
            ->selectRaw('exercise_set_id, MAX(score) AS best')->groupBy('exercise_set_id')->pluck('best', 'exercise_set_id')
            ->map(fn ($v) => (float) $v)->all();
    }
}
