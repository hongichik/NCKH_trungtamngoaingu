<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\LessonVideo;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\VideoProgress;
use App\Support\Video\VideoGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/** V2 bước 4: bài học + video (link ký số, phát theo đoạn, HLS mã hoá, một phiên xem, watermark, tiến độ, lịch mở bài). */
class LessonVideoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Course $course;

    private Classroom $class;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); // không bao giờ đụng file thật trong storage/app/private
        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'boss@x.x', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $this->course = Course::create(['language' => 'en', 'title' => 'IELTS 6.0', 'is_published' => true]);
        $this->class = Classroom::create(['course_id' => $this->course->id, 'name' => 'Lớp A', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $this->student = Student::forceCreate(['last_name' => 'Nguyễn An', 'email' => 'an@hv.vn', 'phone' => '0912345678', 'password' => bcrypt('x')]);
        $this->class->students()->attach($this->student->id);
        // Giữ nguyên một phiên đăng nhập giữa các request (như trình duyệt gửi cookie phiên).
        $this->withCookie(config('session.cookie'), str_repeat('s', 40))->withCredentials();
    }

    private function lesson(array $extra = []): CourseLesson
    {
        $this->actingAs($this->admin)->post(route('admin.courses.lessons.store', $this->course), $extra + ['title' => 'Bài 1: Giới thiệu'])->assertRedirect();

        return CourseLesson::latest('id')->first();
    }

    /** Gửi file theo 2 mảnh như trình duyệt. */
    private function uploadChunks(string $url, string $bytes, array $params): TestResponse
    {
        $half = intdiv(strlen($bytes), 2);
        $q = fn ($i) => $url.'?'.http_build_query($params + ['upload' => 'abc12345xyz', 'part' => $i, 'total' => 2]);
        $this->actingAs($this->admin)->call('POST', $q(0), [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], substr($bytes, 0, $half))
            ->assertJson(['ok' => true, 'complete' => false]);

        return $this->actingAs($this->admin)->call('POST', $q(1), [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], substr($bytes, $half));
    }

    private function mp4Video(CourseLesson $lesson): LessonVideo
    {
        $bytes = str_repeat('0123456789', 5000);   // 50 KB giả lập video
        $this->uploadChunks(route('admin.courses.lessons.video.upload', [$this->course, $lesson]), $bytes, ['name' => 'intro.mp4', 'kind' => 'local', 'duration' => 100])
            ->assertJson(['ok' => true, 'complete' => true]);

        return LessonVideo::latest('id')->first();
    }

    public function test_editors_manage_lessons_videos_and_materials(): void
    {
        $lesson = $this->lesson(['summary' => 'Tóm tắt']);
        $this->actingAs($this->admin)->get(route('admin.courses.lessons.index', $this->course))->assertOk()->assertSee('Bài 1: Giới thiệu');
        $video = $this->mp4Video($lesson);
        $this->assertSame('local', $video->provider);
        $this->assertSame(100, $video->duration_seconds);
        $this->assertSame(50000, $video->size);
        Storage::disk('local')->assertExists($video->path);
        $this->assertStringNotContainsString('public', $video->path);
        $this->actingAs($this->admin)->get(route('admin.courses.lessons.video.preview', [$this->course, $lesson, $video]))->assertOk();

        // Sai định dạng
        $this->actingAs($this->admin)->postJson(route('admin.courses.lessons.video.upload', [$this->course, $lesson]).'?name=x.exe&kind=local&upload=abc12345&part=0&total=1')->assertStatus(422);

        // Phụ đề SRT → VTT, tài liệu
        $this->actingAs($this->admin)->put(route('admin.courses.lessons.video.update', [$this->course, $lesson, $video]), [
            'title' => 'Video giới thiệu', 'captions' => UploadedFile::fake()->createWithContent('a.srt', "1\n00:00:01,000 --> 00:00:02,500\nHello\n"),
        ])->assertSessionHasNoErrors();
        $video->refresh();
        $this->assertStringContainsString('00:00:01.000 --> 00:00:02.500', Storage::disk('local')->get($video->captions_path));
        $this->actingAs($this->admin)->post(route('admin.courses.lessons.material.upload', [$this->course, $lesson]), ['file' => UploadedFile::fake()->create('slide.pdf', 100, 'application/pdf')])->assertSessionHasNoErrors();
        $this->assertSame(1, $lesson->materials()->count());

        // Sắp xếp + tạo bài 2
        $l2 = $this->lesson(['title' => 'Bài 2']);
        $this->actingAs($this->admin)->post(route('admin.courses.lessons.reorder', $this->course), ['order' => [$l2->id, $lesson->id]]);
        $this->assertSame([$l2->id, $lesson->id], $this->course->lessons()->pluck('id')->all());

        // Giáo viên: không có quyền sửa khoá → 403; được cấp → vào được
        $t = Teacher::create(['name' => 'Cô Lan', 'email' => 'lan@gv.vn', 'password' => 'secret123']);
        $this->actingAs($t, 'teacher')->get(route('teacher.lessons.index', $this->course))->assertForbidden();
        $this->course->editors()->attach($t->id);
        $this->actingAs($t, 'teacher')->get(route('teacher.lessons.index', $this->course))->assertOk()->assertSee('Bài 2');
        $this->actingAs($t, 'teacher')->get(route('teacher.lessons.edit', [$this->course, $lesson]))->assertOk()->assertSee('Video giới thiệu');
        $other = Course::create(['language' => 'en', 'title' => 'Khác']);
        $this->actingAs($t, 'teacher')->get(route('teacher.lessons.edit', [$other, $lesson]))->assertForbidden();

        // Xoá video → xoá file
        $path = $video->path;
        $this->actingAs($this->admin)->delete(route('admin.courses.lessons.video.destroy', [$this->course, $lesson, $video]));
        Storage::disk('local')->assertMissing($path);
    }

    public function test_signed_stream_link_is_bound_to_student_session_and_expires(): void
    {
        $lesson = $this->lesson();
        $video = $this->mp4Video($lesson);

        $this->actingAs($this->student, 'student')->get(route('portal.lessons.show', [$this->class, $lesson]))
            ->assertOk()->assertSee('Nguyễn An · an@hv.vn')->assertSee('lp-wm', false)->assertDontSee($video->path);
        $this->actingAs($this->student, 'student')->get(route('portal.classes.show', $this->class))->assertSee('Bài 1: Giới thiệu');

        $src = $this->actingAs($this->student, 'student')->getJson(route('portal.video.source', $video))->assertOk()->assertJsonPath('type', 'mp4')->json('src');
        $this->assertStringContainsString('sig=', $src);

        // Phát theo đoạn
        $r = $this->actingAs($this->student, 'student')->get($src, ['Range' => 'bytes=0-99']);
        $r->assertStatus(206);
        $this->assertSame('bytes 0-99/50000', $r->headers->get('Content-Range'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertStringStartsWith('inline', $r->headers->get('Content-Disposition'));

        // Sửa chữ ký / Referer trang khác / học viên khác dùng link → 403
        $this->actingAs($this->student, 'student')->get(str_replace('sig=', 'sig=x', $src))->assertForbidden();
        $this->actingAs($this->student, 'student')->get($src, ['Referer' => 'https://trang-lay-cap.example/x'])->assertForbidden();
        $other = Student::forceCreate(['last_name' => 'B', 'email' => 'b@hv.vn', 'password' => bcrypt('x')]);
        $this->class->students()->attach($other->id);
        $this->actingAs($other, 'student')->get($src)->assertForbidden();

        // Phiên đăng nhập khác (máy khác) → 403
        $this->withCookie(config('session.cookie'), str_repeat('z', 40))->actingAs($this->student, 'student')->get($src)->assertForbidden();
        $this->withCookie(config('session.cookie'), str_repeat('s', 40));

        // Hết hạn sau vài phút
        $this->travel((int) config('video.link_ttl') + 5)->seconds();
        $this->actingAs($this->student, 'student')->get($src)->assertForbidden();
    }

    public function test_one_viewing_session_at_a_time_with_takeover(): void
    {
        $video = $this->mp4Video($this->lesson());
        $guard = app(VideoGuard::class);
        $this->assertTrue($guard->claimViewer($this->student, 'session-A'));
        $this->assertFalse($guard->claimViewer($this->student, 'session-B'));

        $this->actingAs($this->student, 'student')->getJson(route('portal.video.source', $video))->assertStatus(409)->assertJsonPath('conflict', true);
        $this->actingAs($this->student, 'student')->getJson(route('portal.video.source', $video).'?takeover=1')->assertOk();
        $this->assertFalse($guard->claimViewer($this->student, 'session-A'));

        // Sau 60 giây không còn tín hiệu → phiên khác xem được
        $this->travel(61)->seconds();
        $this->assertTrue($guard->claimViewer($this->student, 'session-A'));
    }

    public function test_hls_package_playlist_is_rewritten_with_signed_segment_and_key_links(): void
    {
        $lesson = $this->lesson();
        $zipPath = tempnam(sys_get_temp_dir(), 'hls');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $zip->addFromString('out/index.m3u8', "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:6\n#EXT-X-KEY:METHOD=AES-128,URI=\"enc.key\"\n#EXTINF:6.0,\nseg0.ts\n#EXTINF:6.0,\nseg1.ts\n#EXT-X-ENDLIST\n");
        $zip->addFromString('out/enc.key', 'K3Y-1234567890AB');
        $zip->addFromString('out/seg0.ts', str_repeat('a', 1000));
        $zip->addFromString('out/seg1.ts', str_repeat('b', 1000));
        $zip->addFromString('../evil.php', '<?php echo 1;');
        $zip->close();
        $this->uploadChunks(route('admin.courses.lessons.video.upload', [$this->course, $lesson]), file_get_contents($zipPath), ['name' => 'video.zip', 'kind' => 'hls'])
            ->assertJson(['ok' => true, 'complete' => true]);
        @unlink($zipPath);
        $video = LessonVideo::sole();
        $this->assertSame('hls', $video->provider);
        Storage::disk('local')->assertMissing(dirname($video->path).'/evil.php');

        $src = $this->actingAs($this->student, 'student')->getJson(route('portal.video.source', $video))->assertJsonPath('type', 'hls')->json('src');
        $playlist = $this->actingAs($this->student, 'student')->get($src)->assertOk()->assertHeader('Content-Type', 'application/vnd.apple.mpegurl')->getContent();
        $this->assertStringNotContainsString("\nseg0.ts", $playlist);
        preg_match('/URI="([^"]+)"/', $playlist, $key);
        preg_match('/^(http\S+f=seg0\.ts\S*)$/m', $playlist, $seg);
        $keyRes = $this->actingAs($this->student, 'student')->get(html_entity_decode($key[1]))->assertOk();
        $this->assertSame('K3Y-1234567890AB', $keyRes->baseResponse->getFile()->getContent());
        $this->actingAs($this->student, 'student')->get($seg[1])->assertOk();
        // Đổi tên file trong link đã ký → 403
        $this->actingAs($this->student, 'student')->get(str_replace('seg0.ts', 'seg1.ts', $seg[1]))->assertForbidden();
    }

    public function test_progress_completion_and_require_video_flag(): void
    {
        $lesson = $this->lesson();
        $lesson->update(['require_video' => true]);
        $video = $this->mp4Video($lesson);
        $url = route('portal.video.progress', $video);

        foreach (range(1, 3) as $i) {
            $this->actingAs($this->student, 'student')->postJson($url, ['position' => $i * 30, 'delta' => 30])->assertOk();
        }
        // Báo gian lận (delta lớn) chỉ được tính tối đa 30 giây mỗi lần
        $r = $this->actingAs($this->student, 'student')->postJson($url, ['position' => 99, 'delta' => 5000])->assertOk();
        $this->assertTrue($r->json('completed'));
        $this->assertTrue($r->json('lesson_done'));
        $p = VideoProgress::sole();
        $this->assertSame(120, $p->watched_seconds);
        $this->assertSame(100, $p->percent);
        $this->assertTrue($lesson->fresh()->videosCompletedBy($this->student->id));
    }

    public function test_schedule_hidden_and_class_period_rules(): void
    {
        $lesson = $this->lesson();
        $video = $this->mp4Video($lesson);

        // Mở vào ngày mai
        $this->actingAs($this->admin)->put(route('admin.classrooms.lesson-schedule', $this->class), ['lessons' => [$lesson->id => ['opens_at' => now()->addDay()->format('Y-m-d H:i')]]]);
        $this->actingAs($this->student, 'student')->get(route('portal.lessons.show', [$this->class, $lesson]))->assertForbidden();
        $this->actingAs($this->student, 'student')->getJson(route('portal.video.source', $video))->assertForbidden();
        $this->actingAs($this->student, 'student')->get(route('portal.classes.show', $this->class))->assertSee('Mở '.now()->addDay()->format('H:i d/m'));

        // Ẩn với lớp
        $this->actingAs($this->admin)->put(route('admin.classrooms.lesson-schedule', $this->class), ['lessons' => [$lesson->id => ['hidden' => 1]]]);
        $this->actingAs($this->student, 'student')->get(route('portal.classes.show', $this->class))->assertDontSee('Bài 1: Giới thiệu');

        // Bỏ lịch → mở ngay; lớp kết thúc → chặn
        $this->actingAs($this->admin)->put(route('admin.classrooms.lesson-schedule', $this->class), ['lessons' => [$lesson->id => []]]);
        $this->actingAs($this->student, 'student')->get(route('portal.lessons.show', [$this->class, $lesson]))->assertOk();
        $this->class->update(['ends_at' => now()->subMinute()]);
        $this->actingAs($this->student, 'student')->get(route('portal.lessons.show', [$this->class, $lesson]))->assertForbidden();

        // Học viên ngoài lớp
        $x = Student::forceCreate(['last_name' => 'X', 'email' => 'x@hv.vn', 'password' => bcrypt('x')]);
        $this->actingAs($x, 'student')->get(route('portal.lessons.show', [$this->class, $lesson]))->assertNotFound();
    }

    public function test_bunny_embed_url_has_expiring_token(): void
    {
        config(['video.bunny.library_id' => '12345', 'video.bunny.token_key' => 'secret-key']);
        $lesson = $this->lesson();
        $this->actingAs($this->admin)->post(route('admin.courses.lessons.video.bunny', [$this->course, $lesson]), ['title' => 'Bunny', 'external_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890'])->assertSessionHasNoErrors();
        $video = LessonVideo::sole();
        $src = $this->actingAs($this->student, 'student')->getJson(route('portal.video.source', $video))->assertJsonPath('type', 'bunny')->json('src');
        parse_str(parse_url($src, PHP_URL_QUERY), $q);
        $this->assertStringStartsWith('https://iframe.mediadelivery.net/embed/12345/a1b2c3d4-e5f6-7890-abcd-ef1234567890', $src);
        $this->assertSame(hash('sha256', 'secret-key'.$video->external_id.$q['expires']), $q['token']);
        $this->assertLessThanOrEqual(time() + 310, (int) $q['expires']);
    }
}
