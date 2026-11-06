<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\CourseLesson;
use App\Models\LessonMaterial;
use App\Models\LessonVideo;
use App\Models\VideoProgress;
use App\Support\Video\VideoGuard;
use App\Support\Video\VideoResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bài học của lớp cho học viên: video (link ký số, phát theo đoạn / HLS mã hoá / Bunny, watermark, tiến độ), tài liệu, ghi chú.
 */
class LessonController extends Controller
{
    public function __construct(private VideoGuard $guard) {}

    private function student()
    {
        return auth('student')->user();
    }

    /** Lớp của học viên phải đang mở và bài học phải mở với lớp. */
    private function authorizeLesson(Classroom $classroom, CourseLesson $lesson): void
    {
        abort_unless($classroom->students()->whereKey($this->student()->id)->exists(), 404);
        abort_unless($lesson->course_id === $classroom->course_id, 404);
        abort_unless($classroom->accessOpen(), 403, $classroom->closedReason() ?? 'Lớp không mở.');
        abort_unless($lesson->openFor($classroom), 403, 'Bài học chưa mở với lớp của bạn.');
    }

    /** Học viên được xem video này không: thuộc một lớp đang mở của khoá và bài học mở với lớp đó. */
    private function canWatch(LessonVideo $video): bool
    {
        $lesson = $video->lesson;

        return $this->student()->classrooms()->where('course_id', $lesson->course_id)->get()
            ->contains(fn (Classroom $c) => $c->accessOpen() && $lesson->openFor($c));
    }

    public function show(Classroom $classroom, CourseLesson $lesson): View
    {
        $this->authorizeLesson($classroom, $lesson);
        $lesson->load('videos', 'materials', 'unit.profile');
        $sid = $this->student()->id;
        $progress = VideoProgress::whereIn('lesson_video_id', $lesson->videos->pluck('id'))->where('student_id', $sid)->get()->keyBy('lesson_video_id');
        $lessons = $classroom->course->lessons()->where('is_published', true)->get(['id', 'title', 'position', 'course_id', 'is_published']);

        return view('lessons.portal.show', [
            'classroom' => $classroom, 'lesson' => $lesson, 'progress' => $progress,
            'videosDone' => $lesson->videosCompletedBy($sid),
            'profile' => $lesson->unit?->profile?->data ?? [],
            'prev' => $lessons->where('position', '<', $lesson->position)->last(),
            'next' => $lessons->where('position', '>', $lesson->position)->first(),
        ]);
    }

    /** Cấp link phát mới (gọi khi mở trang và khi link sắp / đã hết hạn). */
    public function source(Request $request, LessonVideo $video): JsonResponse
    {
        abort_unless($this->canWatch($video), 403);
        $student = $this->student();
        $session = $request->session()->getId();
        if ($request->boolean('takeover')) {
            $this->guard->takeOver($student, $session);
        } elseif (! $this->guard->claimViewer($student, $session)) {
            return response()->json(['ok' => false, 'conflict' => true, 'message' => 'Tài khoản của bạn đang xem video ở thiết bị / trình duyệt khác.'], 409);
        }

        $src = match ($video->provider) {
            'local' => route('portal.video.stream', ['video' => $video] + $this->guard->params('stream', $video, $student, $session)),
            'hls' => route('portal.video.hls', ['video' => $video] + $this->guard->params('hls', $video, $student, $session, basename($video->path))),
            'bunny' => $this->guard->bunnyEmbedUrl($video),
        };
        $p = VideoProgress::where('lesson_video_id', $video->id)->where('student_id', $student->id)->first();

        return response()->json([
            'ok' => true, 'type' => $video->provider === 'local' ? 'mp4' : $video->provider, 'src' => $src,
            'captions' => $video->captions_path ? route('portal.video.captions', ['video' => $video] + $this->guard->params('vtt', $video, $student, $session)) : null,
            'position' => $p && ! $p->completed_at ? $p->last_position : 0,
            'ttl' => (int) config('video.link_ttl'),
        ]);
    }

    /** MP4 phát theo đoạn (HTTP Range). */
    public function stream(Request $request, LessonVideo $video): BinaryFileResponse|Response
    {
        $this->checkSigned($request, 'stream', $video);

        return VideoResponse::file(Storage::disk('local')->path($video->path));
    }

    /** Gói HLS: playlist (viết lại thành link ký số), đoạn video, khoá AES-128. */
    public function hls(Request $request, LessonVideo $video): BinaryFileResponse|Response
    {
        $this->checkSigned($request, 'hls', $video);
        $file = basename((string) $request->query('f'));
        abort_unless($video->provider === 'hls' && preg_match('/^[A-Za-z0-9._-]+$/', $file), 404);
        $abs = Storage::disk('local')->path(dirname($video->path).'/'.$file);
        abort_unless(is_file($abs), 404);

        if (str_ends_with($file, '.m3u8')) {
            $student = $this->student();
            $session = $request->session()->getId();
            $body = $this->guard->rewritePlaylist((string) file_get_contents($abs), fn ($name) => route('portal.video.hls', ['video' => $video] + $this->guard->params('hls', $video, $student, $session, $name)));

            return response($body, 200, ['Content-Type' => 'application/vnd.apple.mpegurl', 'Cache-Control' => 'private, no-store']);
        }

        return VideoResponse::file($abs);
    }

    public function captions(Request $request, LessonVideo $video): Response
    {
        $this->checkSigned($request, 'vtt', $video);
        abort_unless($video->captions_path, 404);

        return response(Storage::disk('local')->get($video->captions_path), 200, ['Content-Type' => 'text/vtt; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function checkSigned(Request $request, string $kind, LessonVideo $video): void
    {
        abort_unless($this->guard->verify($request, $kind, $video, $this->student()), 403, 'Link video đã hết hạn hoặc không dùng được ở đây. Tải lại trang để xem tiếp.');
        abort_unless($this->guard->refererOk($request), 403);
        abort_unless($this->canWatch($video), 403);
    }

    /** Báo tiến độ xem: position (giây), delta (số giây đã xem từ lần báo trước, tối đa 30), duration. */
    public function progress(Request $request, LessonVideo $video): JsonResponse
    {
        abort_unless($this->canWatch($video), 403);
        $data = $request->validate([
            'position' => ['required', 'numeric', 'min:0'],
            'delta' => ['required', 'numeric', 'min:0'],
            'duration' => ['nullable', 'numeric', 'min:1'],
        ]);
        $student = $this->student();
        $this->guard->claimViewer($student, $request->session()->getId());
        if (! $video->duration_seconds && ! empty($data['duration'])) {
            $video->update(['duration_seconds' => (int) round($data['duration'])]);
        }
        $p = VideoProgress::firstOrNew(['lesson_video_id' => $video->id, 'student_id' => $student->id]);
        $p->watched_seconds = (int) $p->watched_seconds + (int) min(30, round($data['delta']));
        $p->last_position = (int) $data['position'];
        $duration = (int) ($video->duration_seconds ?: ($data['duration'] ?? 0));
        $p->percent = $duration ? (int) min(100, floor($p->watched_seconds / $duration * 100)) : 0;
        if (! $p->completed_at && $p->percent >= (int) config('video.complete_percent', 90)) {
            $p->completed_at = now();
        }
        $p->save();

        return response()->json(['ok' => true, 'percent' => $p->percent, 'completed' => (bool) $p->completed_at,
            'lesson_done' => $video->lesson->videosCompletedBy($student->id)]);
    }

    public function material(LessonMaterial $material): BinaryFileResponse
    {
        $lesson = $material->lesson;
        abort_unless($this->student()->classrooms()->where('course_id', $lesson->course_id)->get()->contains(fn ($c) => $c->accessOpen() && $lesson->openFor($c)), 403);
        $ext = strtolower(pathinfo($material->path, PATHINFO_EXTENSION));
        $inline = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'mp3', 'm4a'], true);

        return response()->file(Storage::disk('local')->path($material->path), [
            'Content-Type' => $material->mime ?: 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.Str::slug($material->title).'.'.$ext.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
