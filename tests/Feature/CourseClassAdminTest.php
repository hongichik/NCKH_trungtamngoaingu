<?php

namespace Tests\Feature;

use App\Models\AccountLog;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\ClassroomCustomExercise;
use App\Models\Course;
use App\Models\CustomAttempt;
use App\Models\CustomExercise;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UnitProfile;
use App\Models\UnitTest;
use App\Models\User;
use App\Support\Students\StudentImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** V2 bước 2–3: khoá học & lớp do quản trị viên quản lý, phân quyền giáo viên, lớp có thời hạn, nhập học viên hàng loạt. */
class CourseClassAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private Teacher $lan;

    private Teacher $minh;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); // không bao giờ đụng file thật trong storage/app/private
        $this->owner = User::forceCreate(['id' => 1, 'name' => 'Owner', 'email' => 'admin@gmail.com', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'boss@x.x', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $this->lan = Teacher::create(['name' => 'Cô Lan', 'email' => 'lan@gv.vn', 'password' => 'secret123']);
        $this->minh = Teacher::create(['name' => 'Thầy Minh', 'email' => 'minh@gv.vn', 'password' => 'secret123']);
    }

    /** Không còn tải nguyên quyển giáo trình: tạo khoá → vào trang bài học, tự thêm từng bài; route giáo trình đã gỡ. */
    public function test_course_is_built_lesson_by_lesson_without_textbook_upload(): void
    {
        $this->actingAs($this->admin)->get(route('admin.courses.create'))->assertOk()->assertDontSee('giáo trình');
        $this->actingAs($this->admin)->post(route('admin.courses.store'), ['title' => 'HSK 1', 'language' => 'zh', 'setup' => 'upload'])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.courses.lessons.index', $c = Course::sole()));
        $this->actingAs($this->admin)->get(route('admin.courses.lessons.index', $c))->assertOk()->assertSee('Thêm bài học')->assertDontSee('Tải giáo trình')->assertDontSee('Unit');
        $this->assertFalse(Route::has('admin.textbooks.create'));
        $this->assertFalse(Route::has('teacher.textbooks.index'));
        $this->actingAs($this->admin)->get('/admin/giao-trinh/tai-len')->assertNotFound();
    }

    /** Xoá bài học: phải xác nhận trong hộp cảnh báo; hộp ghi rõ số bài tập / video / lượt làm bài sẽ mất. */
    public function test_delete_lesson_requires_confirmation_and_shows_what_will_be_lost(): void
    {
        $course = Course::create(['language' => 'en', 'title' => 'A2', 'is_published' => true]);
        $lesson = $course->lessons()->create(['title' => 'Unit 1 — Family', 'position' => 1]);
        $ex = CustomExercise::create(['course_id' => $course->id, 'lesson_id' => $lesson->id, 'title' => 'Bài 1', 'status' => 'published', 'created_by_type' => 'user', 'created_by_id' => $this->admin->id, 'created_by_name' => 'A']);
        Storage::fake('local');
        Storage::disk('local')->put("custom-exercises/{$ex->id}/images/1.jpg", 'x');
        $class = Classroom::create(['course_id' => $course->id, 'name' => 'Lớp A']);
        $cce = ClassroomCustomExercise::create(['classroom_id' => $class->id, 'custom_exercise_id' => $ex->id]);
        $student = Student::forceCreate(['last_name' => 'An', 'email' => 'an@hv.vn', 'password' => 'x12345678']);
        CustomAttempt::forceCreate(['classroom_custom_exercise_id' => $cce->id, 'student_id' => $student->id, 'status' => 'graded']);

        $page = $this->actingAs($this->admin)->get(route('admin.courses.lessons.index', $course))->assertOk();
        $page->assertSee('data-delete-lesson="'.route('admin.courses.lessons.destroy', [$course, $lesson]).'"', false)
            ->assertSee('data-exercises="1"', false)->assertSee('data-attempts="1"', false)
            ->assertSee('Xoá bài học?')->assertSee('Tôi hiểu và muốn xoá bài học này');
        $this->actingAs($this->admin)->get(route('admin.courses.lessons.edit', [$course, $lesson]))->assertOk()->assertSee('data-attempts="1"', false);

        // Không tick xác nhận → không xoá
        $this->actingAs($this->admin)->delete(route('admin.courses.lessons.destroy', [$course, $lesson]))->assertSessionHasErrors('lesson');
        $this->assertModelExists($lesson);

        $this->actingAs($this->admin)->delete(route('admin.courses.lessons.destroy', [$course, $lesson]), ['confirm' => 1])
            ->assertRedirect(route('admin.courses.lessons.index', $course));
        $this->assertModelMissing($lesson);
        $this->assertModelMissing($ex);
        Storage::disk('local')->assertMissing("custom-exercises/{$ex->id}/images/1.jpg");
    }

    private function makeCourseAndClass(array $class = []): Classroom
    {
        $this->actingAs($this->admin)->post(route('admin.courses.store'), ['title' => 'IELTS 6.0', 'language' => 'en', 'level' => 'B2', 'is_published' => 1, 'editors' => [$this->lan->id]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $course = Course::sole();

        $this->actingAs($this->admin)->post(route('admin.classrooms.store'), $class + [
            'name' => 'IELTS tối 2-4-6', 'course_id' => $course->id,
            'starts_at' => now()->subDay()->format('Y-m-d H:i'), 'ends_at' => now()->addMonth()->format('Y-m-d H:i'),
            'teachers' => [
                ['id' => $this->lan->id, 'can_customize' => 1, 'can_add_students' => 1, 'can_reset_password' => 1],
                ['id' => $this->minh->id, 'can_reset_password' => 1],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        return Classroom::sole();
    }

    // ---------------------------------------------------------------- bước 2

    public function test_admin_creates_course_and_timed_class_with_two_teachers_having_different_permissions(): void
    {
        $c = $this->makeCourseAndClass();
        $course = $c->course;

        $this->assertSame($this->admin->id, $course->created_by);
        $this->assertTrue($this->lan->canEditCourse($course));
        $this->assertFalse($this->minh->canEditCourse($course));
        $this->assertSame('active', $c->status());
        $this->assertFalse($c->allow_join, 'Link mời mặc định tắt (quyết định 4)');
        $this->assertSame($this->lan->id, $c->teacher_id);
        $this->assertTrue($this->lan->hasClassPermission($c, 'can_add_students'));
        $this->assertFalse($this->minh->hasClassPermission($c, 'can_add_students'));
        $this->assertTrue($this->minh->hasClassPermission($c, 'can_reset_password'));

        // Trang quản trị
        $this->actingAs($this->admin)->get(route('admin.courses.index'))->assertOk()->assertSee('IELTS 6.0')->assertSee('Cô Lan');
        $this->actingAs($this->admin)->get(route('admin.classrooms.index', ['status' => 'active']))->assertOk()->assertSee('IELTS tối 2-4-6');
        $this->actingAs($this->admin)->get(route('admin.classrooms.index', ['status' => 'ended']))->assertOk()->assertDontSee('IELTS tối 2-4-6');
        $this->actingAs($this->admin)->get(route('admin.classrooms.show', $c))->assertOk()->assertSee('Thầy Minh');
        $this->actingAs($this->admin)->get(route('admin.classrooms.edit', $c))->assertOk()->assertSee('Chỉ chủ hệ thống đổi được');
        $this->actingAs($this->admin)->get(route('admin.courses.edit', $course))->assertOk();

        // Mỗi giáo viên chỉ thấy đúng phần mình
        $other = Classroom::create(['course_id' => $course->id, 'name' => 'Lớp không gán']);
        $this->actingAs($this->minh, 'teacher')->get('/giao-vien/lop-hoc')->assertOk()->assertSee('IELTS tối 2-4-6')->assertDontSee('Lớp không gán');
        $this->actingAs($this->minh, 'teacher')->get("/giao-vien/lop-hoc/{$other->id}")->assertNotFound();
        $this->actingAs($this->minh, 'teacher')->get("/giao-vien/lop-hoc/{$c->id}")->assertOk()->assertDontSee('Thêm học viên đã có tài khoản');
        $this->actingAs($this->lan, 'teacher')->get("/giao-vien/lop-hoc/{$c->id}")->assertOk()->assertSee('Thêm học viên đã có tài khoản');
        $this->actingAs($this->lan, 'teacher')->get('/giao-vien/khoa-hoc')->assertOk()->assertSee('IELTS 6.0');
        $this->actingAs($this->minh, 'teacher')->get('/giao-vien/khoa-hoc')->assertOk()->assertSee('chưa được cấp quyền');

        // Giáo viên không tự tạo / xoá lớp được nữa
        $this->actingAs($this->lan, 'teacher')->post('/giao-vien/lop-hoc', ['name' => 'X', 'course_id' => $course->id])->assertStatus(405);
        $this->actingAs($this->lan, 'teacher')->delete("/giao-vien/lop-hoc/{$c->id}")->assertStatus(405);

        // Giáo viên không có quyền thêm học viên
        $this->actingAs($this->minh, 'teacher')->post("/giao-vien/lop-hoc/{$c->id}/nhap-hoc-vien", ['rows' => "A\ta@x.vn"])->assertForbidden();

        // Nhân viên không có quyền "Khoá học & lớp học"
        $staff = User::forceCreate(['name' => 'Staff', 'email' => 's@x.x', 'password' => bcrypt('x'), 'is_admin' => 0]);
        $this->actingAs($staff)->get(route('admin.classrooms.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.courses.store'), ['title' => 'X', 'language' => 'en'])->assertForbidden();

        // Khoá đang có lớp vẫn xoá được: lớp giữ lại, bỏ gắn khoá
        $this->actingAs($this->admin)->delete(route('admin.courses.destroy', $course))->assertRedirect(route('admin.courses.index'));
        $this->assertModelMissing($course);
        $this->assertNull($c->fresh()->course_id);
    }

    public function test_class_period_blocks_students_and_only_owner_can_allow_access_after_end(): void
    {
        $c = $this->makeCourseAndClass();
        [$student, $assignment] = $this->studentWithAssignment($c);

        // Đang học → làm được
        $this->actingAs($student, 'student')->post("/thi-sinh/bai-duoc-giao/{$assignment->id}/lam-bai")->assertRedirect();
        $test = UnitTest::sole();

        // Hết giờ lớp → bị chặn, vẫn xem được kết quả cũ
        $c->update(['ends_at' => now()->subHour(), 'starts_at' => now()->subMonth()]);
        $this->actingAs($student, 'student')->post("/thi-sinh/bai-duoc-giao/{$assignment->id}/lam-bai")->assertSessionHas('status_error', fn ($m) => str_contains($m, 'đã kết thúc'));
        $this->actingAs($student, 'student')->get("/thi-sinh/hoc-tap/{$c->course_id}/lam-bai/{$test->id}")->assertForbidden();
        $this->actingAs($student, 'student')->get("/thi-sinh/lop-hoc/{$c->id}")->assertOk()->assertSee('Đã kết thúc')->assertDontSee('Làm bài</button>', false);
        $this->actingAs($student, 'student')->get("/thi-sinh/hoc-tap/{$c->course_id}/lam-bai/{$test->id}/ket-qua")->assertOk();

        // Quản trị viên (không phải chủ hệ thống) gửi allow_after_end → bị bỏ qua
        $payload = ['name' => $c->name, 'course_id' => $c->course_id, 'starts_at' => $c->starts_at->format('Y-m-d H:i'), 'ends_at' => $c->ends_at->format('Y-m-d H:i'),
            'allow_after_end' => 1, 'grace_days' => 7, 'teachers' => [['id' => $this->lan->id, 'can_add_students' => 1]]];
        $this->actingAs($this->admin)->put(route('admin.classrooms.update', $c), $payload)->assertSessionHasNoErrors();
        $this->assertFalse($c->fresh()->allow_after_end);

        // Chủ hệ thống bật → học viên vào lại được trong 7 ngày gia hạn
        $this->actingAs($this->owner)->put(route('admin.classrooms.update', $c), $payload)->assertSessionHasNoErrors();
        $c->refresh();
        $this->assertTrue($c->allow_after_end);
        $this->assertSame(7, $c->grace_days);
        $this->assertTrue($c->accessOpen());
        $this->actingAs($student, 'student')->get("/thi-sinh/hoc-tap/{$c->course_id}/lam-bai/{$test->id}")->assertOk();
        $this->travel(8)->days();
        $this->assertFalse($c->fresh()->accessOpen());
        $this->travelBack();

        // Lớp chưa bắt đầu → chặn
        $c->update(['starts_at' => now()->addDay(), 'ends_at' => now()->addMonth(), 'allow_after_end' => false]);
        $this->actingAs($student, 'student')->post("/thi-sinh/bai-duoc-giao/{$assignment->id}/lam-bai")->assertSessionHas('status_error', fn ($m) => str_contains($m, 'bắt đầu'));
        $this->assertSame('upcoming', $c->fresh()->status());

        // Gỡ gán giáo viên Minh khi cập nhật → Minh mất quyền vào lớp
        $this->actingAs($this->minh, 'teacher')->get("/giao-vien/lop-hoc/{$c->id}")->assertNotFound();
    }

    private function studentWithAssignment(Classroom $c): array
    {
        $book = $this->lan->textbooks()->create(['title' => 'B', 'language' => 'en', 'source_type' => 'pdf', 'status' => 'ready', 'page_count' => 1]);
        $c->course->update(['textbook_id' => $book->id]);
        $unit = $book->units()->create(['position' => 1, 'title' => 'Unit 1', 'start_page' => 1, 'end_page' => 1, 'status' => 'ready']);
        UnitProfile::create(['unit_id' => $unit->id, 'data' => ['vocabulary' => [], 'grammar_points' => [], 'passages' => [], 'dialogues' => []]]);
        $set = $unit->exerciseSets()->create(['skill' => 'reading', 'title' => 'Đọc', 'status' => 'published', 'published_at' => now(), 'source' => 'textbook', 'matrix' => [], 'data' => ['passage' => 'Tom has two friends.']]);
        $set->questions()->create(['position' => 1, 'code' => 'R1', 'type' => 'mcq', 'prompt' => 'How many?', 'options' => ['A. one', 'B. two'], 'answer' => 'B']);
        $student = Student::forceCreate(['last_name' => 'An', 'email' => 'an@hv.vn', 'password' => bcrypt('x')]);
        $c->students()->attach($student->id);
        $this->actingAs($this->lan, 'teacher')->post("/giao-vien/lop-hoc/{$c->id}/giao-bai", ['unit_id' => $unit->id, 'skills' => ['reading'], 'mode' => 'practice', 'show_answers' => 'after_submit'])->assertSessionHasNoErrors();

        return [$student, Assignment::sole()];
    }

    // ---------------------------------------------------------------- bước 3

    public function test_import_preview_statuses_and_commit_without_duplicates(): void
    {
        $c = $this->makeCourseAndClass();
        $existing = Student::forceCreate(['last_name' => 'Cũ Email', 'email' => 'cu@hv.vn', 'password' => bcrypt('x')]);
        $byPhone = Student::forceCreate(['last_name' => 'Cũ SĐT', 'email' => 'sdt@hv.vn', 'phone' => '0912 000 111', 'password' => bcrypt('x')]);
        $inClass = Student::forceCreate(['last_name' => 'Trong lớp', 'email' => 'trong@hv.vn', 'password' => bcrypt('x')]);
        $c->students()->attach($inClass->id);

        $rows = "Họ tên\tEmail\tSố điện thoại\tNgày sinh\n"
            ."Nguyễn Mới\tmoi@hv.vn\t0987654321\t15/08/2008\n"
            ."Cũ Email\tCU@hv.vn\t\t\n"
            ."Ai đó\tkhac@hv.vn\t+84 912 000 111\t\n"
            ."Trong lớp\ttrong@hv.vn\t\t\n"
            ."Thiếu email\t\t\t\n"
            ."Sai email\tabc\t\t\n"
            ."Trùng\tmoi@hv.vn\t\t\n"
            ."Ngày sai\tngay@hv.vn\t\t31/31/2000\n";

        $html = $this->actingAs($this->lan, 'teacher')->post("/giao-vien/lop-hoc/{$c->id}/nhap-hoc-vien", ['rows' => $rows])->assertOk()
            ->assertSee('Đã có tài khoản')->assertSee('Thiếu email')->assertSee('Email sai định dạng')->assertSee('Trùng email với dòng 2')
            ->assertSee('Trùng số điện thoại với tài khoản sdt@hv.vn')->assertSee('Ngày sinh sai')->getContent();
        $preview = app(StudentImport::class)->preview(app(StudentImport::class)->parse($rows), $c);
        $this->assertSame(['new' => 1, 'existing' => 2, 'in_class' => 1, 'error' => 4], StudentImport::counts($preview));

        $rowsJson = json_encode(app(StudentImport::class)->parse($rows));
        $this->actingAs($this->lan, 'teacher')->post("/giao-vien/lop-hoc/{$c->id}/nhap-hoc-vien/luu", ['rows' => $rowsJson, 'password_mode' => 'phone'])
            ->assertOk()->assertSee('Đã tạo')->assertSee('0987654321');
        $this->assertSame(4, Student::count(), 'Chỉ tạo 1 tài khoản mới');
        $new = Student::where('email', 'moi@hv.vn')->sole();
        $this->assertTrue($new->must_change_password);
        $this->assertSame('2008-08-15', $new->dob->toDateString());
        $this->assertTrue(Hash::check('0987654321', $new->password));
        $this->assertSame(4, $c->students()->count());   // trong lớp + mới + 2 tài khoản cũ
        $this->assertTrue($c->students()->whereKey($byPhone->id)->exists());
        $this->assertTrue($c->students()->whereKey($existing->id)->exists());

        // Tải CSV tài khoản vừa tạo
        $csv = $this->actingAs($this->lan, 'teacher')->get("/giao-vien/lop-hoc/{$c->id}/nhap-hoc-vien/tai-khoan.csv")->assertOk()->getContent();
        $this->assertStringContainsString('moi@hv.vn,0987654321,0987654321', $csv);

        // Nhập lại cùng file: không tạo trùng
        $this->actingAs($this->lan, 'teacher')->post("/giao-vien/lop-hoc/{$c->id}/nhap-hoc-vien/luu", ['rows' => $rowsJson, 'password_mode' => 'random'])->assertOk();
        $this->assertSame(4, Student::count());

        // Nhật ký
        $this->assertSame(1, AccountLog::where('action', 'create')->count());
        $this->assertSame('Cô Lan', AccountLog::where('action', 'create')->sole()->actor_name);
        $this->assertSame('teacher', AccountLog::where('action', 'create')->sole()->actor_type);
    }

    public function test_import_200_rows_mixed_with_existing_accounts_by_admin(): void
    {
        $c = $this->makeCourseAndClass();
        foreach (range(1, 50) as $i) {
            Student::forceCreate(['last_name' => "Cũ {$i}", 'email' => "hv{$i}@x.vn", 'password' => bcrypt('x')]);
        }
        $lines = collect(range(1, 200))->map(fn ($i) => "Học viên {$i},hv{$i}@x.vn,09".str_pad((string) $i, 8, '0', STR_PAD_LEFT).',')->implode("\n");
        $file = UploadedFile::fake()->createWithContent('ds.csv', "\xEF\xBB\xBFHọ tên,Email,Số điện thoại,Ngày sinh\n".$lines);

        $this->actingAs($this->admin)->post(route('admin.classrooms.import', $c), ['file' => $file])->assertOk()->assertSee('Lưu: tạo 150 tài khoản, thêm 200 học viên');
        $rows = app(StudentImport::class)->parse($lines);
        $this->actingAs($this->admin)->post(route('admin.classrooms.import.commit', $c), ['rows' => json_encode($rows), 'password_mode' => 'random'])->assertOk();
        $this->assertSame(200, Student::count());
        $this->assertSame(200, $c->students()->count());
        $this->assertSame(150, Student::where('must_change_password', true)->count());
        $this->assertSame('user', AccountLog::where('action', 'create')->first()->actor_type);
    }

    public function test_xlsx_import_and_template(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Họ tên</t></si><si><t>Email</t></si><si><t>Trần Bình</t></si><si><t>binh@x.vn</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>'
            .'<row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2" t="s"><v>3</v></c><c r="C2"><v>912345678</v></c><c r="D2"><v>39310</v></c></row>'
            .'</sheetData></worksheet>');
        $zip->close();

        $rows = app(StudentImport::class)->parse(null, new UploadedFile($path, 'ds.xlsx', null, null, true));
        $this->assertSame([['name' => 'Trần Bình', 'email' => 'binh@x.vn', 'phone' => '912345678', 'dob' => '16/08/2007', 'line' => 2]], $rows);
        @unlink($path);

        $this->actingAs($this->admin)->get(route('admin.students.import-template'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_first_login_must_change_password_and_teacher_reset_needs_permission(): void
    {
        $c = $this->makeCourseAndClass();
        $s = Student::forceCreate(['last_name' => 'Hoa', 'email' => 'hoa@hv.vn', 'password' => 'matkhau1', 'must_change_password' => true]);
        $c->students()->attach($s->id);

        $this->post('/dang-ky-chung-chi/dang-nhap', ['email' => 'hoa@hv.vn', 'password' => 'matkhau1']);
        $this->get('/thi-sinh/lop-hoc')->assertRedirect(route('portal.password.edit'));
        $this->get(route('portal.password.edit'))->assertOk()->assertSee('đặt mật khẩu mới');
        $this->post(route('portal.password.update'), ['current_password' => 'sai', 'password' => 'moi12345', 'password_confirmation' => 'moi12345'])->assertSessionHasErrors('current_password');
        $this->post(route('portal.password.update'), ['current_password' => 'matkhau1', 'password' => 'moi12345', 'password_confirmation' => 'moi12345'])->assertRedirect(route('portal.home'));
        $this->assertFalse($s->fresh()->must_change_password);
        $this->get('/thi-sinh/lop-hoc')->assertOk();

        // Giáo viên Minh có quyền cấp lại mật khẩu; bỏ quyền thì không được
        $this->actingAs($this->minh, 'teacher')->post("/giao-vien/lop-hoc/{$c->id}/hoc-vien/{$s->id}/cap-lai-mat-khau")
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Mật khẩu mới của Hoa'));
        $this->assertTrue($s->fresh()->must_change_password);
        $this->assertSame('reset_password', AccountLog::latest('id')->first()->action);
        $c->teachers()->updateExistingPivot($this->minh->id, ['can_reset_password' => false]);
        $this->actingAs($this->minh, 'teacher')->post("/giao-vien/lop-hoc/{$c->id}/hoc-vien/{$s->id}/cap-lai-mat-khau")->assertForbidden();

        // Học viên ngoài lớp → 404
        $outsider = Student::forceCreate(['last_name' => 'Ngoài', 'email' => 'ngoai@hv.vn', 'password' => bcrypt('x')]);
        $this->actingAs($this->lan, 'teacher')->post("/giao-vien/lop-hoc/{$c->id}/hoc-vien/{$outsider->id}/cap-lai-mat-khau")->assertNotFound();

        // Quản trị viên cấp lại + nhật ký hiện ở trang lớp
        $this->actingAs($this->admin)->post(route('admin.classrooms.students.reset', [$c, $s]))->assertSessionHas('status');
        $this->actingAs($this->admin)->get(route('admin.classrooms.show', $c))->assertSee('Cấp lại mật khẩu')->assertSee('Thầy Minh');
    }

    public function test_v1_data_migration_rules(): void
    {
        // Lớp / khoá tạo kèm giáo viên (như dữ liệu V1) → giáo viên được gán đủ quyền / quyền sửa khoá
        $course = Course::create(['teacher_id' => $this->lan->id, 'language' => 'en', 'title' => 'V1']);
        $c = Classroom::create(['teacher_id' => $this->lan->id, 'course_id' => $course->id, 'name' => 'V1 class']);
        $this->assertTrue($this->lan->canEditCourse($course));
        foreach (array_keys(Classroom::PERMS) as $p) {
            $this->assertTrue($this->lan->hasClassPermission($c, $p), $p);
        }
        // Xoá giáo viên không còn xoá lớp
        $this->lan->delete();
        $this->assertNotNull($c->fresh());
        $this->assertNull($c->fresh()->teacher_id);
    }
}
