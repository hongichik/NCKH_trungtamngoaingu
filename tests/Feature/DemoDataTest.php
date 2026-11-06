<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CustomAttempt;
use App\Models\CustomExercise;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Lệnh dữ liệu mẫu tạo đúng mô hình Khoá học → Bài học → Bài tập và mọi trang liên quan hiển thị được. */
class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_command_builds_usable_data_and_all_pages_render(): void
    {
        Storage::fake('local');
        $t = Teacher::create(['name' => 'GV', 'email' => 'gv@gmail.com', 'password' => 'secret123']);
        $s = Student::forceCreate(['last_name' => 'HV', 'email' => 'hv@gmail.com', 'password' => bcrypt('x')]);
        $this->artisan('demo:learning-data', ['--student' => 'hv@gmail.com'])->assertSuccessful();
        $this->artisan('demo:learning-data', ['--student' => 'hv@gmail.com'])->assertSuccessful(); // chạy lại được

        $this->assertSame(2, Course::count());
        $this->assertSame(2, Classroom::count());
        $this->assertSame(5, CourseLesson::count());
        $this->assertTrue(CustomExercise::all()->every(fn ($e) => $e->lesson_id !== null), 'Mọi bài tập đều nằm trong bài học');
        $this->assertSame(3, CustomAttempt::count());

        // Học viên
        $st = $this->actingAs($s, 'student');
        foreach (['/thi-sinh', '/thi-sinh/lop-hoc', '/thi-sinh/tien-do'] as $u) {
            $st->get($u)->assertOk();
        }
        foreach (Classroom::all() as $c) {
            $st->get(route('portal.classes.show', $c))->assertOk()->assertSee('Bài học');
            foreach ($c->course->lessons as $lesson) {
                $st->get(route('portal.lessons.show', [$c, $lesson]))->assertOk()->assertSee('Bài tập của bài học')->assertSee('Làm bài');
                foreach ($lesson->exercisesFor($c)->get() as $ex) {
                    $st->post(route('portal.custom.start-exercise', [$c, $ex]))->assertRedirect();
                    $st->get(route('portal.custom.do', CustomAttempt::latest('id')->first()))->assertOk();
                }
            }
        }

        // Giáo viên
        $gv = $this->actingAs($t, 'teacher');
        $gv->get('/giao-vien/khoa-hoc')->assertOk()->assertSee('English A2');
        foreach (Classroom::all() as $c) {
            $gv->get(route('teacher.classrooms.show', $c))->assertOk()->assertSee('Bài học & bài tập của lớp', false);
        }
        foreach (Course::all() as $course) {
            $gv->get(route('teacher.lessons.index', $course))->assertOk();
            foreach ($course->lessons as $lesson) {
                $gv->get(route('teacher.lessons.edit', [$course, $lesson]))->assertOk();
            }
        }
        foreach (CustomExercise::all() as $ex) {
            $gv->get(route('teacher.custom.edit', [$ex->course_id, $ex]))->assertOk();
            $gv->get(route('teacher.custom.preview', [$ex->course_id, $ex]))->assertOk();
        }
        $en = Classroom::where('name', 'like', '%Tiếng Anh%')->sole();
        $first = $en->course->lessons()->first()->exercises()->first();
        $gv->get(route('teacher.classrooms.custom.open', [$en, $first]))->assertRedirect();

        // Quản trị viên
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'boss@x.x', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $ad = $this->actingAs($admin);
        $ad->get(route('admin.courses.index'))->assertOk();
        foreach (Course::all() as $course) {
            $ad->get(route('admin.courses.edit', $course))->assertOk();
            $ad->get(route('admin.courses.lessons.index', $course))->assertOk()->assertSee('Thêm bài học')->assertDontSee('Tải giáo trình');
        }
        foreach (Classroom::all() as $c) {
            $ad->get(route('admin.classrooms.show', $c))->assertOk();
        }
    }
}
