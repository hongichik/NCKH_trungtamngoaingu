<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Contact;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Rà soát bảo mật: chống dò mật khẩu, chống spam liên hệ, trang lỗi tiếng Việt, luồng đăng nhập học viên. */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('admin-login:127.0.0.1|a@x.vn');
        RateLimiter::clear('portal-login:127.0.0.1|hv@x.vn');
        RateLimiter::clear('portal-register:127.0.0.1');
    }

    public function test_admin_login_is_throttled_after_repeated_failures(): void
    {
        User::forceCreate(['id' => 1, 'name' => 'A', 'email' => 'a@x.vn', 'password' => bcrypt('dung-mat-khau'), 'is_admin' => 1]);
        for ($i = 0; $i < 8; $i++) {
            $this->post('/admin/login', ['email' => 'a@x.vn', 'password' => 'sai'])->assertSessionHasErrors('email');
        }
        // Lần 9 đúng mật khẩu vẫn bị chặn vì đã khoá tạm.
        $this->post('/admin/login', ['email' => 'a@x.vn', 'password' => 'dung-mat-khau'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('quá nhiều lần', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_student_login_throttled_and_register_rate_limited(): void
    {
        Student::forceCreate(['last_name' => 'HV', 'email' => 'hv@x.vn', 'password' => bcrypt('dung-mat-khau')]);
        for ($i = 0; $i < 8; $i++) {
            $this->post(route('portal.login'), ['email' => 'hv@x.vn', 'password' => 'sai']);
        }
        $this->post(route('portal.login'), ['email' => 'hv@x.vn', 'password' => 'dung-mat-khau'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('quá nhiều lần', session('errors')->first('email'));
        $this->assertGuest('student');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('portal.register'), ['full_name' => 'N '.$i, 'email' => "n{$i}@x.vn", 'phone' => '0900000000', 'password' => 'matkhau1', 'password_confirmation' => 'matkhau1']);
            auth('student')->logout();
        }
        $this->post(route('portal.register'), ['full_name' => 'N 9', 'email' => 'n9@x.vn', 'phone' => '0900000000', 'password' => 'matkhau1', 'password_confirmation' => 'matkhau1'])
            ->assertSessionHasErrors('email');
        $this->assertSame(6, Student::count());
    }

    public function test_contact_honeypot_and_throttle(): void
    {
        $this->post(route('contact.store'), ['name' => 'Bot', 'email' => 'b@x.vn', 'content' => 'spam', 'website' => 'http://spam'])->assertSessionHas('status');
        $this->assertSame(0, Contact::count());
        $this->post(route('contact.store'), ['name' => 'Người', 'email' => 'p@x.vn', 'content' => 'Hỏi lịch khai giảng'])->assertSessionHas('status');
        $this->assertSame(1, Contact::count());
        $this->post(route('contact.store'), ['name' => 'Dài', 'email' => 'p@x.vn', 'content' => str_repeat('a', 3001)])->assertSessionHasErrors('content');
        $this->get(route('contact.create'))->assertOk()->assertSee('name="website"', false);
    }

    public function test_vietnamese_error_pages(): void
    {
        $this->get('/khong-ton-tai-abc')->assertNotFound()->assertSee('Không tìm thấy trang');
        $this->get('/admin')->assertRedirect(); // chưa đăng nhập
        User::forceCreate(['id' => 1, 'name' => 'Owner', 'email' => 'owner@x.vn', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $staff = User::forceCreate(['name' => 'S', 'email' => 's@x.vn', 'password' => bcrypt('x'), 'is_admin' => 0]);
        $this->actingAs($staff)->get(route('admin.system.index'))->assertForbidden()->assertSee('Không có quyền truy cập')->assertSee('Chỉ chủ hệ thống');
    }

    public function test_learning_student_lands_on_portal_home_without_exam_profile(): void
    {
        $student = Student::forceCreate(['last_name' => 'HV', 'email' => 'hv@x.vn', 'password' => bcrypt('dung-mat-khau'), 'must_change_password' => false]);
        $course = Course::create(['language' => 'en', 'title' => 'K', 'is_published' => true]);
        Classroom::create(['course_id' => $course->id, 'name' => 'L'])->students()->attach($student->id);
        $this->post(route('portal.login'), ['email' => 'hv@x.vn', 'password' => 'dung-mat-khau'])->assertRedirect(route('portal.home'));
        auth('student')->logout();
        // Vào đúng luồng đăng ký thi thì vẫn phải hoàn thiện hồ sơ.
        $this->post(route('portal.login'), ['email' => 'hv@x.vn', 'password' => 'dung-mat-khau', 'type' => 'cntt'])->assertRedirect(route('portal.profile.edit', ['type' => 'cntt']));
    }

    public function test_staff_with_module_permissions_sees_only_their_pages(): void
    {
        User::forceCreate(['id' => 1, 'name' => 'Owner', 'email' => 'owner@x.vn', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $staff = User::forceCreate(['name' => 'NV', 'email' => 'nv@x.vn', 'password' => bcrypt('x'), 'is_admin' => 0]);
        $staff->permissions()->sync(\App\Models\Permission::whereIn('module', ['posts', 'contacts', 'exam_registrations', 'certificates', 'students'])->whereNotIn('action', ['delete'])->pluck('id'));
        $contact = Contact::create(['name' => 'K', 'email' => 'k@x.vn', 'content' => 'Hỏi', 'status' => 'unread']);
        foreach ([route('admin.dashboard'), route('admin.posts.index'), route('admin.posts.create'), route('admin.contacts.index'), route('admin.contacts.show', $contact),
            route('admin.exam-registrations.index', ['type' => 'cntt']), route('admin.certificates.index'), route('admin.students.index')] as $url) {
            $this->actingAs($staff)->get($url)->assertOk();
        }
        $html = $this->actingAs($staff)->get(route('admin.dashboard'))->getContent();
        $this->assertStringNotContainsString(route('admin.teachers.index'), $html, 'Menu không được hiện mục ngoài quyền');
        foreach ([route('admin.users.index'), route('admin.teachers.index'), route('admin.courses.index'), route('admin.settings.edit')] as $url) {
            $this->actingAs($staff)->get($url)->assertForbidden()->assertSee('Không có quyền truy cập');
        }
    }
}
