<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\ClassroomCustomExercise;
use App\Models\Contact;
use App\Models\Course;
use App\Models\CustomAttempt;
use App\Models\CustomExercise;
use App\Models\ExamLevel;
use App\Models\ExamSession;
use App\Models\Gallery;
use App\Models\LessonMaterial;
use App\Models\Page;
use App\Models\Post;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UnitProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

/**
 * Quét khói: mọi route GET có tên, mở bằng đúng vai (chủ hệ thống / giáo viên / học viên / khách)
 * trên dữ liệu demo đầy đủ — không trang nào được lỗi 500.
 */
class SmokeSweepTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_named_get_route_renders_without_server_error(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        User::forceCreate(['id' => 1, 'name' => 'Owner', 'email' => 'owner@gmail.com', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $this->artisan('tutorial:demo', ['action' => 'setup', '--no-ai' => true])->assertSuccessful();
        $this->artisan('demo:learning-data', ['--student' => 'demo.hocvien@tutorial.local'])->assertSuccessful();

        $owner = User::find(1);
        $teacher = Teacher::where('email', 'like', 'demo.giaovien@%')->firstOrFail();
        $student = Student::where('email', 'like', 'demo.hocvien@%')->firstOrFail();
        $exercise = CustomExercise::whereHas('items')->firstOrFail();
        $course = Course::findOrFail($exercise->course_id);
        $classroom = $student->classrooms()->where('course_id', $course->id)->firstOrFail();
        $classroom->teachers()->syncWithoutDetaching([$teacher->id => ['can_customize' => true, 'can_add_students' => true, 'can_reset_password' => true]]);
        $lesson = $course->lessons()->orderBy('position')->firstOrFail();
        $item = $exercise->items()->firstOrFail();
        $cce = ClassroomCustomExercise::where('classroom_id', $classroom->id)->first() ?? ClassroomCustomExercise::create(['classroom_id' => $classroom->id, 'custom_exercise_id' => $exercise->id, 'lesson_id' => $exercise->lesson_id]);
        $attempt = CustomAttempt::where('student_id', $student->id)->first()
            ?? CustomAttempt::create(['classroom_custom_exercise_id' => $cce->id, 'student_id' => $student->id, 'status' => 'in_progress']);
        $video = $lesson->videos()->first() ?? $lesson->videos()->create(['title' => 'V', 'provider' => 'local', 'path' => 'x.mp4']);
        $material = $lesson->materials()->first() ?? LessonMaterial::create(['lesson_id' => $lesson->id, 'title' => 'M', 'path' => 'm.pdf']);

        // Dữ liệu V1 (tin tức, trang, thư viện ảnh, chứng chỉ, kỳ thi, giáo trình) để các trang đó thật sự được dựng.
        $cat = Category::create(['name' => 'Tin tức', 'slug' => 'tin-tuc-chung']);
        $post = Post::create(['title' => 'Bài viết', 'slug' => 'bai-viet', 'status' => 'published', 'content' => '<p>Nội dung</p>']);
        $post->categories()->attach($cat->id);
        $page = Page::create(['title' => 'Giới thiệu', 'slug' => 'gioi-thieu', 'status' => 'published', 'content' => '<p>GT</p>']);
        $gallery = Gallery::create(['name' => 'Ảnh', 'slug' => 'anh']);
        $contact = Contact::create(['name' => 'Khách', 'email' => 'k@x.vn', 'content' => 'Hỏi']);
        $certificate = Certificate::create(['student_id' => $student->id, 'serial_number' => 'SN001', 'issue_date' => now()->toDateString(), 'status' => 'published']);
        $level = ExamLevel::create(['exam_type' => 'cntt', 'name' => 'Cơ bản', 'price' => 500000, 'is_active' => true]);
        $session = ExamSession::create(['exam_type' => 'cntt', 'name' => 'Đợt 1', 'exam_date' => now()->addMonth()->toDateString(), 'status' => 'open']);
        $book = $teacher->textbooks()->create(['title' => 'English 1', 'language' => 'en', 'level' => 'A2', 'source_type' => 'pdf', 'original_name' => 'a.pdf', 'status' => 'ready', 'page_count' => 10]);
        $unit = $book->units()->create(['position' => 1, 'title' => 'Unit 1', 'start_page' => 1, 'end_page' => 5, 'status' => 'ready']);
        UnitProfile::create(['unit_id' => $unit->id, 'data' => ['vocabulary' => [['word' => 'friend', 'meaning_vi' => 'bạn']], 'grammar_points' => [], 'passages' => [], 'dialogues' => []]]);
        $set = $unit->exerciseSets()->create(['skill' => 'reading', 'title' => 'Đọc 1', 'status' => 'published', 'published_at' => now(), 'source' => 'textbook', 'teacher_id' => $teacher->id, 'matrix' => [], 'data' => ['passage' => 'Tom has two friends.']]);
        $set->questions()->create(['position' => 1, 'code' => 'R1', 'type' => 'mcq', 'prompt' => 'How many?', 'options' => ['A. one', 'B. two'], 'answer' => 'B', 'explanation' => 'x', 'evidence' => 'x']);
        $course->update(['textbook_id' => $book->id]);
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'unit_id' => $unit->id, 'title' => 'Bài Unit 1', 'mode' => 'practice', 'sections' => [['skill' => 'reading', 'set_id' => $set->id]], 'show_answers' => 'after_submit']);

        $params = [
            'course' => $course->id, 'classroom' => $classroom->id, 'lesson' => $lesson->id, 'exercise' => $exercise->id, 'item' => $item->id,
            'cce' => $cce->id, 'attempt' => $attempt->id, 'video' => $video->id, 'material' => $material->id, 'student' => $student->id,
            'teacher' => $teacher->id, 'user' => $owner->id, 'code' => 'general.mcq_single', 'section' => 0, 'audioId' => 'a1',
            'type' => 'cntt', 'kind' => 'students', 'name' => 'none.gz', 'slug' => $page->slug, 'serialNumber' => 'SN001', 'token' => 'x', 'tab' => 'overview',
            'category' => $cat->slug, 'post' => $post->id, 'page' => $page->id, 'gallery' => $gallery->slug, 'contact' => $contact->id, 'certificate' => $certificate->id,
            'session' => $session->id, 'unit' => $unit->id, 'set' => $set->id, 'assignment' => $assignment->id, 'menu' => 1, 'banner' => 1,
        ];

        $failures = [];
        $seen = 0;
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || ! $route->getName() || str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            $mw = $route->gatherMiddleware();
            $name = $route->getName();
            $url = route($name, collect($route->parameterNames())->mapWithKeys(fn ($n) => [$n => $params[$n] ?? 1])->all());
            $seen++;
            $actor = match (true) {
                // actingAs(…, guard) đổi guard mặc định, nên lần nào cũng nêu rõ guard.
                in_array('teacher', $mw, true) => fn () => $this->actingAs($teacher, 'teacher'),
                in_array('student.auth', $mw, true) => fn () => $this->actingAs($student, 'student'),
                collect($mw)->contains(fn ($m) => str_starts_with($m, 'auth')) || str_starts_with($name, 'admin.') => fn () => $this->actingAs($owner, 'web'),
                default => fn () => $this,
            };
            try {
                $res = $actor()->withoutExceptionHandling()->get($url);
                $status = $res->baseResponse->getStatusCode();
                $body = '';
            } catch (ModelNotFoundException|FileNotFoundException $e) {
                $status = 404;
                $body = '';
            } catch (HttpExceptionInterface $e) {
                $status = $e->getStatusCode();
                $body = '';
            } catch (ValidationException|\Illuminate\Auth\AuthenticationException|TokenMismatchException $e) {
                $status = 302;
                $body = '';
            } catch (\Throwable $e) {
                $status = 500;
                $body = get_class($e).': '.$e->getMessage().' @ '.str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine();
            }
            if ($status >= 500) {
                $failures[] = "[$status] $name  $url\n      ".preg_replace('/\s+/', ' ', $body);
            }
        }
        $this->assertGreaterThan(120, $seen);
        $this->assertSame([], $failures, "Trang lỗi máy chủ:\n".implode("\n", $failures));
    }
}
