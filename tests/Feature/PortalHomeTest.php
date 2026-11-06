<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_home_works_with_and_without_class(): void
    {
        $this->get('/thi-sinh')->assertRedirect();

        $s = Student::forceCreate(['last_name' => 'Nguyễn An', 'email' => 'an@hv.vn', 'password' => bcrypt('x')]);
        $this->actingAs($s, 'student')->get('/thi-sinh')->assertOk()
            ->assertSee('Xin chào')->assertSee('Đăng ký thi HSK')->assertSee('Đăng ký thi Tin học')
            ->assertSee('Hoàn thiện hồ sơ')->assertDontSee('Lớp học của tôi');

        $t = Teacher::create(['name' => 'Cô Lan', 'email' => 'lan@gv.vn', 'password' => 'secret123']);
        $c = Classroom::create(['teacher_id' => $t->id, 'name' => 'Lớp 10A']);
        $c->students()->attach($s->id);
        $this->actingAs($s, 'student')->get('/thi-sinh')->assertOk()->assertSee('Lớp học của tôi')->assertSee('Lớp 10A')->assertSee('Cô Lan');
    }
}
