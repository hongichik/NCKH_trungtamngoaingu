<?php

namespace Tests\Feature;

use App\Models\AiImport;
use App\Models\Classroom;
use App\Models\ClassroomCustomExercise;
use App\Models\Course;
use App\Models\CustomAttempt;
use App\Models\CustomExercise;
use App\Models\LessonMaterial;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\QuestionTypes\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** V2 bước 7: quét mọi route GET mới — người không có quyền không bao giờ nhận 200. */
class V2PermissionSweepTest extends TestCase
{
    use RefreshDatabase;

    private array $params;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); // không bao giờ đụng file thật trong storage/app/private
        User::forceCreate(['id' => 1, 'name' => 'Owner', 'email' => 'admin@gmail.com', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $course = Course::create(['language' => 'en', 'title' => 'K', 'is_published' => true]);
        $class = Classroom::create(['course_id' => $course->id, 'name' => 'L']);
        $lesson = $course->lessons()->create(['title' => 'B1']);
        $video = $lesson->videos()->create(['title' => 'V', 'provider' => 'local', 'path' => 'x.mp4']);
        $material = LessonMaterial::create(['lesson_id' => $lesson->id, 'title' => 'M', 'path' => 'm.pdf']);
        $ex = CustomExercise::create(['course_id' => $course->id, 'title' => 'E', 'status' => 'published', 'created_by_type' => 'user', 'created_by_id' => 1]);
        $item = $ex->items()->create(['type_code' => 'general.mcq_single', 'content' => Registry::get('general.mcq_single')->sample, 'audio_path' => 'a.mp3']);
        $cce = ClassroomCustomExercise::create(['classroom_id' => $class->id, 'custom_exercise_id' => $ex->id]);
        $owner = Student::forceCreate(['last_name' => 'Chủ', 'email' => 'chu@hv.vn', 'password' => bcrypt('x')]);
        $attempt = CustomAttempt::create(['classroom_custom_exercise_id' => $cce->id, 'student_id' => $owner->id, 'status' => 'graded']);
        $import = AiImport::create(['course_id' => $course->id, 'title' => 'I', 'status' => 'review', 'created_by_type' => 'user', 'created_by_id' => 1, 'audio' => [['id' => 'a1', 'name' => 'x.mp3', 'path' => 'x.mp3']]]);
        $this->params = ['course' => $course->id, 'classroom' => $class->id, 'lesson' => $lesson->id, 'video' => $video->id, 'material' => $material->id,
            'exercise' => $ex->id, 'item' => $item->id, 'cce' => $cce->id, 'attempt' => $attempt->id, 'import' => $import->id, 'audioId' => 'a1',
            'student' => $owner->id, 'code' => 'general.mcq_single', 'section' => 0];
    }

    private function getRoutes(array $prefixes): array
    {
        return collect(Route::getRoutes())->filter(fn ($r) => in_array('GET', $r->methods(), true) && $r->getName()
            && collect($prefixes)->contains(fn ($p) => str_starts_with($r->getName(), $p)))
            ->map(fn ($r) => route($r->getName(), collect($r->parameterNames())->mapWithKeys(fn ($n) => [$n => $this->params[$n] ?? 1])->all()))
            ->values()->all();
    }

    public function test_staff_without_learning_permission_and_non_owner_admin(): void
    {
        $staff = User::forceCreate(['name' => 'S', 'email' => 's@x.x', 'password' => bcrypt('x'), 'is_admin' => 0]);
        $urls = $this->getRoutes(['admin.courses.', 'admin.classrooms.', 'admin.students.import']);
        $this->assertGreaterThan(20, count($urls));
        foreach ($urls as $u) {
            $this->assertNotSame(200, $this->actingAs($staff)->get($u)->status(), "Nhân viên không có quyền vào được {$u}");
        }
        $admin = User::forceCreate(['name' => 'A', 'email' => 'a@x.x', 'password' => bcrypt('x'), 'is_admin' => 1]);
        foreach ($this->getRoutes(['admin.system.']) as $u) {
            $this->assertSame(403, $this->actingAs($admin)->get($u)->status(), "Quản trị viên (không phải chủ hệ thống) vào được {$u}");
        }
    }

    public function test_teacher_without_any_assignment_or_course_right(): void
    {
        $t = Teacher::create(['name' => 'T', 'email' => 't@gv.vn', 'password' => 'secret123']);
        $urls = $this->getRoutes(['teacher.lessons.', 'teacher.custom.', 'teacher.ai-import.', 'teacher.classrooms.']);
        $urls = array_filter($urls, fn ($u) => ! str_ends_with(parse_url($u, PHP_URL_PATH), '/lop-hoc') && ! str_contains($u, 'file-mau'));
        $this->assertGreaterThan(12, count($urls));
        foreach ($urls as $u) {
            $this->assertContains($this->actingAs($t, 'teacher')->get($u)->status(), [403, 404], "Giáo viên không có quyền vào được {$u}");
        }
    }

    public function test_student_outside_class_and_guests(): void
    {
        $x = Student::forceCreate(['last_name' => 'Ngoài', 'email' => 'x@hv.vn', 'password' => bcrypt('x')]);
        $urls = $this->getRoutes(['portal.lessons.', 'portal.video.', 'portal.custom.']);
        $this->assertGreaterThan(8, count($urls));
        foreach ($urls as $u) {
            $this->assertContains($this->actingAs($x, 'student')->get($u)->status(), [403, 404, 405], "Học viên ngoài lớp vào được {$u}");
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach ($this->getRoutes(['admin.courses.', 'admin.classrooms.', 'teacher.custom.', 'teacher.lessons.', 'portal.custom.', 'portal.lessons.', 'portal.video.']) as $u) {
            $this->assertContains($this->get($u)->status(), [302, 401, 403, 404], "Khách vào được {$u}");
        }
    }
}
