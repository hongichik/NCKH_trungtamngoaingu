<?php

namespace Tests\Feature;

use App\Models\AccountLog;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Trang quản lý tài khoản học viên + tìm học viên đã có để thêm vào lớp. */
class StudentAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Classroom $class;

    private Student $account;

    private Student $record;

    private Student $noEmail;

    protected function setUp(): void
    {
        parent::setUp();
        User::forceCreate(['id' => 1, 'name' => 'Owner', 'email' => 'admin@gmail.com', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'boss@x.x', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $course = Course::create(['language' => 'en', 'title' => 'IELTS', 'is_published' => true]);
        $this->class = Classroom::create(['course_id' => $course->id, 'name' => 'IELTS tối 2-4-6']);
        $this->account = Student::forceCreate(['last_name' => 'Nguyễn Văn An', 'email' => 'an@hv.vn', 'phone' => '0912345678', 'password' => 'matkhau1']);
        // Hồ sơ thí sinh đăng ký thi: có email, chưa có mật khẩu
        $this->record = Student::forceCreate(['last_name' => 'Trần', 'first_name' => 'Thị Bình', 'email' => 'binh@thisinh.vn', 'identity_number' => '001203004567']);
        $this->noEmail = Student::forceCreate(['last_name' => 'Lê', 'first_name' => 'Cường', 'identity_number' => '001203009999']);
    }

    public function test_list_search_scopes_and_permission(): void
    {
        $this->actingAs($this->admin)->get(route('admin.student-accounts.index'))->assertOk()
            ->assertSee('Nguyễn Văn An')->assertDontSee('Thị Bình');                     // mặc định: chỉ tài khoản đăng nhập được
        $this->actingAs($this->admin)->get(route('admin.student-accounts.index', ['scope' => 'records']))->assertSee('Thị Bình')->assertSee('Cường');
        $this->actingAs($this->admin)->get(route('admin.student-accounts.index', ['scope' => 'records', 'q' => '001203004567']))->assertSee('Thị Bình')->assertDontSee('Cường');
        $this->actingAs($this->admin)->get(route('admin.student-accounts.index', ['q' => '0912 345']))->assertSee('Nguyễn Văn An');
        $this->actingAs($this->admin)->get(route('admin.student-accounts.index', ['scope' => 'no_class']))->assertSee('Nguyễn Văn An');
        $this->actingAs($this->admin)->get(route('admin.student-accounts.show', $this->account))->assertOk()->assertSee('Chưa vào lớp nào');

        $staff = User::forceCreate(['name' => 'S', 'email' => 's@x.x', 'password' => bcrypt('x'), 'is_admin' => 0]);
        $this->actingAs($staff)->get(route('admin.student-accounts.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.student-accounts.lock', $this->account))->assertForbidden();
    }

    public function test_create_single_and_bulk_without_class(): void
    {
        $this->actingAs($this->admin)->post(route('admin.student-accounts.store'), ['name' => 'Phạm Dũng', 'email' => 'DUNG@hv.vn', 'classroom_id' => $this->class->id])
            ->assertRedirect()->assertSessionHas('status', fn ($m) => str_contains($m, 'mật khẩu:'));
        $d = Student::where('email', 'dung@hv.vn')->sole();
        $this->assertTrue($d->must_change_password);
        $this->assertTrue($this->class->students()->whereKey($d->id)->exists());
        $this->actingAs($this->admin)->post(route('admin.student-accounts.store'), ['name' => 'X', 'email' => 'binh@thisinh.vn'])->assertSessionHasErrors('email');

        $rows = "Họ tên\tEmail\nMai Lan\tlan@hv.vn\nNguyễn Văn An\tan@hv.vn";
        $this->actingAs($this->admin)->post(route('admin.student-accounts.import'), ['rows' => $rows])->assertOk()->assertSee('Lưu: tạo 1 tài khoản');
        $this->actingAs($this->admin)->post(route('admin.student-accounts.import.commit'), ['rows' => json_encode([['name' => 'Mai Lan', 'email' => 'lan@hv.vn', 'phone' => '', 'dob' => '', 'line' => 2], ['name' => 'Nguyễn Văn An', 'email' => 'an@hv.vn', 'phone' => '', 'dob' => '', 'line' => 3]]), 'password_mode' => 'random'])
            ->assertOk()->assertSee('lan@hv.vn');
        $this->assertNotNull(Student::where('email', 'lan@hv.vn')->value('password'));
        $this->assertSame(0, Student::where('email', 'lan@hv.vn')->sole()->classrooms()->count());
        $this->assertStringContainsString('lan@hv.vn', $this->actingAs($this->admin)->get(route('admin.student-accounts.import.credentials'))->getContent());
    }

    public function test_reset_lock_and_class_membership_from_account_page(): void
    {
        // Khoá → không đăng nhập được; đang đăng nhập thì bị đăng xuất
        $this->actingAs($this->admin)->post(route('admin.student-accounts.lock', $this->account))->assertSessionHas('status');
        $this->assertTrue($this->account->fresh()->login_locked);
        $this->post('/dang-ky-chung-chi/dang-nhap', ['email' => 'an@hv.vn', 'password' => 'matkhau1'])->assertSessionHasErrors('email');
        $this->assertGuest('student');
        $this->actingAs($this->account->fresh(), 'student')->get('/thi-sinh/lop-hoc')->assertRedirect(route('portal.auth'));
        $this->actingAs($this->admin)->post(route('admin.student-accounts.lock', $this->account));
        $this->post('/dang-ky-chung-chi/dang-nhap', ['email' => 'an@hv.vn', 'password' => 'matkhau1']);
        $this->assertAuthenticated('student');
        $this->assertNotNull($this->account->fresh()->last_login_at);
        auth('student')->logout();

        // Cấp lại mật khẩu
        $this->actingAs($this->admin)->post(route('admin.student-accounts.reset', $this->account))->assertSessionHas('status', fn ($m) => str_contains($m, 'Mật khẩu mới'));
        $this->assertFalse(Hash::check('matkhau1', $this->account->fresh()->password));

        // Hồ sơ thí sinh (có email, chưa mật khẩu) → thêm vào lớp thì được cấp mật khẩu
        $this->actingAs($this->admin)->post(route('admin.student-accounts.classes.add', $this->record), ['classroom_id' => $this->class->id])->assertSessionHas('status', fn ($m) => str_contains($m, 'Đã cấp mật khẩu'));
        $this->assertNotNull($this->record->fresh()->password);
        $this->assertTrue($this->class->students()->whereKey($this->record->id)->exists());
        $this->actingAs($this->admin)->delete(route('admin.student-accounts.classes.remove', [$this->record, $this->class]));
        $this->assertFalse($this->class->students()->whereKey($this->record->id)->exists());

        // Hồ sơ chưa có email → không thêm được
        $this->actingAs($this->admin)->post(route('admin.student-accounts.classes.add', $this->noEmail), ['classroom_id' => $this->class->id])->assertStatus(422);

        $this->assertEqualsCanonicalizing(['lock', 'unlock', 'reset_password'], AccountLog::where('student_id', $this->account->id)->pluck('action')->all());
        $this->actingAs($this->admin)->get(route('admin.student-accounts.show', $this->account))->assertSee('Khoá đăng nhập')->assertSee('Cấp lại mật khẩu');
    }

    public function test_class_page_search_and_add_existing_students(): void
    {
        $this->class->students()->attach($this->account->id);

        $res = $this->actingAs($this->admin)->getJson(route('admin.classrooms.students.search', [$this->class, 'q' => 'n']))->assertOk()->json('results');
        $this->assertSame([], $res, 'Cần ít nhất 2 ký tự');
        $res = collect($this->actingAs($this->admin)->getJson(route('admin.classrooms.students.search', [$this->class, 'q' => '0012030']))->json('results'))->keyBy('id');
        $this->assertTrue($res[$this->record->id]['can_add']);
        $this->assertFalse($res[$this->record->id]['has_login']);
        $this->assertFalse($res[$this->noEmail->id]['can_add']);
        $this->assertTrue(collect($this->actingAs($this->admin)->getJson(route('admin.classrooms.students.search', [$this->class, 'q' => 'an@hv']))->json('results'))->first()['in_class']);

        $this->actingAs($this->admin)->post(route('admin.classrooms.students.add-existing', $this->class), ['student_ids' => [$this->record->id, $this->noEmail->id, $this->account->id]])
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Đã thêm 1 học viên') && str_contains($m, 'binh@thisinh.vn') && str_contains($m, 'chưa có email'));
        $this->assertSame(2, $this->class->students()->count());
        $this->assertTrue($this->record->fresh()->must_change_password);
        $this->actingAs($this->admin)->get(route('admin.classrooms.show', $this->class))->assertSee('Thêm học viên đã có tài khoản');

        // Giáo viên: cần quyền "thêm học viên" ở lớp
        $t = Teacher::create(['name' => 'Cô Lan', 'email' => 'lan@gv.vn', 'password' => 'secret123']);
        $this->class->teachers()->attach($t->id, ['can_add_students' => false]);
        $this->actingAs($t, 'teacher')->getJson(route('teacher.classrooms.students.search', [$this->class, 'q' => 'Cường']))->assertForbidden();
        $this->actingAs($t, 'teacher')->get(route('teacher.classrooms.show', $this->class))->assertDontSee('Thêm học viên đã có tài khoản');
        $this->class->teachers()->updateExistingPivot($t->id, ['can_add_students' => true]);
        $this->actingAs($t, 'teacher')->getJson(route('teacher.classrooms.students.search', [$this->class, 'q' => 'Cường']))->assertOk()->assertJsonPath('results.0.can_add', false);
        $this->actingAs($t, 'teacher')->get(route('teacher.classrooms.show', $this->class))->assertSee('Thêm học viên đã có tài khoản');
    }
}
