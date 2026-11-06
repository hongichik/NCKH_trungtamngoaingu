<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseSet;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TextbookUnit;
use App\Models\UnitProfile;
use App\Models\UnitTest;
use App\Support\Exercise\RecordingPruner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClassroomTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $teacher;

    private Course $course;

    private TextbookUnit $unit;

    private ExerciseSet $set;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = Teacher::create(['name' => 'Cô Lan', 'email' => 'lan@gv.vn', 'password' => 'secret123']);
        $book = $this->teacher->textbooks()->create(['title' => 'English 1', 'language' => 'en', 'level' => 'B1', 'source_type' => 'pdf', 'original_name' => 'a.pdf', 'status' => 'ready', 'page_count' => 10]);
        $this->unit = $book->units()->create(['position' => 1, 'title' => 'Unit 1: Friends', 'start_page' => 1, 'end_page' => 5, 'status' => 'ready']);
        UnitProfile::create(['unit_id' => $this->unit->id, 'data' => ['vocabulary' => [], 'grammar_points' => [], 'passages' => [], 'dialogues' => []]]);
        // Khoá học CHƯA xuất bản — học viên vẫn làm được bài giao vì thuộc lớp
        $this->course = Course::create(['teacher_id' => $this->teacher->id, 'textbook_id' => $book->id, 'language' => 'en', 'title' => 'English 1', 'is_published' => false]);

        $this->set = $this->unit->exerciseSets()->create(['skill' => 'reading', 'title' => 'Đọc', 'status' => 'published', 'published_at' => now(), 'source' => 'textbook', 'teacher_id' => $this->teacher->id, 'matrix' => [], 'data' => ['passage' => 'Tom has two friends. They play football every day after school.']]);
        foreach ([['R1', 'B', 'How many friends?'], ['R2', 'C', 'When do they play?']] as $i => [$code, $ans, $prompt]) {
            $this->set->questions()->create(['position' => $i + 1, 'code' => $code, 'type' => 'mcq', 'prompt' => $prompt, 'options' => ['A. one', 'B. two', 'C. after school', 'D. never'], 'answer' => $ans, 'explanation' => "Giải thích {$code}", 'evidence' => 'Tom has two friends.']);
        }
        $this->student = Student::forceCreate(['last_name' => 'Nguyễn An', 'email' => 'an@hv.vn', 'password' => bcrypt('x')]);
    }

    private function makeClass(): Classroom
    {
        // V2: quản trị viên tạo lớp; teacher_id → giáo viên được gán với đủ quyền.
        return Classroom::create(['teacher_id' => $this->teacher->id, 'course_id' => $this->course->id, 'name' => 'B1 tối 2-4-6', 'allow_join' => true]);
    }

    private function assign(Classroom $c, array $extra = []): Assignment
    {
        $this->actingAs($this->teacher, 'teacher')->post("/giao-vien/lop-hoc/{$c->id}/giao-bai", $extra + [
            'unit_id' => $this->unit->id, 'skills' => ['reading', 'listening'], 'mode' => 'practice', 'show_answers' => 'after_submit',
        ])->assertSessionHasNoErrors()->assertRedirect();

        return Assignment::latest('id')->first();
    }

    public function test_teacher_creates_class_students_join_by_invite_link_or_email(): void
    {
        $c = $this->makeClass();
        $this->assertSame(32, strlen($c->invite_token));
        $this->actingAs($this->teacher, 'teacher')->get("/giao-vien/lop-hoc/{$c->id}")->assertOk()->assertSee($c->inviteUrl());

        // Chưa vào lớp nào → menu không có "Lớp học của tôi"
        $this->actingAs($this->student, 'student')->get('/thi-sinh/thong-tin')->assertDontSee('Lớp học của tôi');

        // Học viên mở link mời → xác nhận → vào lớp
        $this->actingAs($this->student, 'student')->get("/thi-sinh/vao-lop/{$c->invite_token}")->assertOk()->assertSee($c->name)->assertSee('Vào lớp');
        $this->actingAs($this->student, 'student')->post("/thi-sinh/vao-lop/{$c->invite_token}")->assertRedirect("/thi-sinh/lop-hoc/{$c->id}");
        $this->assertTrue($c->students()->whereKey($this->student->id)->exists());
        $this->actingAs($this->student, 'student')->get("/thi-sinh/vao-lop/{$c->invite_token}")->assertRedirect("/thi-sinh/lop-hoc/{$c->id}");
        $this->actingAs($this->student, 'student')->get('/thi-sinh/thong-tin')->assertSee('Lớp học của tôi');
        $this->actingAs($this->student, 'student')->get('/thi-sinh/vao-lop/saisaisai')->assertOk()->assertSee('không còn hiệu lực');

        // V2: giáo viên không đổi được tên lớp / link mời (chỉ bảng xếp hạng)
        $old = $c->invite_token;
        $this->actingAs($this->teacher, 'teacher')->put("/giao-vien/lop-hoc/{$c->id}", ['name' => 'Đổi tên', 'allow_join' => 0, 'new_link' => 1, 'show_leaderboard' => 1]);
        $c->refresh();
        $this->assertSame($old, $c->invite_token);
        $this->assertSame('B1 tối 2-4-6', $c->name);
        $this->assertTrue($c->allow_join);
        $this->assertTrue($c->show_leaderboard);

        // Giáo viên thêm bằng email
        $b = Student::forceCreate(['last_name' => 'Bình', 'email' => 'binh@hv.vn', 'password' => bcrypt('x')]);
        $this->actingAs($this->teacher, 'teacher')->post("/giao-vien/lop-hoc/{$c->id}/hoc-vien", ['emails' => "BINH@hv.vn\nkhongco@hv.vn"])
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Đã thêm 1') && str_contains($m, 'khongco@hv.vn'));
        $this->assertSame(2, $c->students()->count());

        // Khoá link mời
        $c->update(['allow_join' => false]);
        $x = Student::forceCreate(['last_name' => 'X', 'email' => 'x@hv.vn', 'password' => bcrypt('x')]);
        $this->actingAs($x, 'student')->post("/thi-sinh/vao-lop/{$c->invite_token}");
        $this->assertFalse($c->students()->whereKey($x->id)->exists());

        // Giáo viên khác không vào được lớp
        $other = Teacher::create(['name' => 'B', 'email' => 'b@gv.vn', 'password' => 'secret123']);
        $this->actingAs($other, 'teacher')->get("/giao-vien/lop-hoc/{$c->id}")->assertNotFound();
    }

    public function test_assignment_snapshots_published_sets_and_student_completes_it(): void
    {
        $c = $this->makeClass();
        $c->students()->attach($this->student->id);
        $a = $this->assign($c);

        // Chỉ kỹ năng đã có bộ xuất bản (Đọc) được đưa vào
        $this->assertSame([['skill' => 'reading', 'set_id' => $this->set->id]], $a->sections);

        $s = $this->actingAs($this->student, 'student');
        $s->get("/thi-sinh/lop-hoc/{$c->id}")->assertOk()->assertSee($a->title)->assertSee('Chưa làm');
        $s->get('/thi-sinh/lop-hoc')->assertOk()->assertSee($c->name);

        $s->post("/thi-sinh/bai-duoc-giao/{$a->id}/lam-bai")->assertRedirect();
        $test = UnitTest::sole();
        $this->assertSame($a->id, $test->assignment_id);
        $s->get("/thi-sinh/hoc-tap/{$this->course->id}/lam-bai/{$test->id}")->assertOk()->assertSee('How many friends?')->assertSee('Luyện tập');

        $s->post("/thi-sinh/hoc-tap/{$this->course->id}/lam-bai/{$test->id}/nop", ['answers' => [$this->set->id => ['R1' => 'B', 'R2' => 'A']]])->assertRedirect();
        $test->refresh();
        $this->assertSame('completed', $test->status);
        $this->assertEquals(5, $test->total_score);
        $s->get("/thi-sinh/lop-hoc/{$c->id}")->assertSee('Đã nộp')->assertSee('Trung bình');

        // Bảng điểm + xuất Excel
        $t = $this->actingAs($this->teacher, 'teacher');
        $t->get("/giao-vien/bai-giao/{$a->id}")->assertOk()->assertSee('Nguyễn An')->assertSee('1/1')->assertSee('When do they play?'); // câu nhiều người sai
        $csv = $t->get("/giao-vien/bai-giao/{$a->id}/xuat-excel")->assertOk()->streamedContent();
        $this->assertStringContainsString('Nguyễn An', $csv);
        $this->assertStringContainsString('an@hv.vn', $csv);

        // Chấm lại: sửa điểm phần Đọc → điểm tổng tính lại
        $attempt = ExerciseAttempt::sole();
        $t->put("/giao-vien/bai-giao/{$a->id}/cham-lai/{$attempt->id}", ['teacher_score' => 8, 'teacher_comment' => 'Đọc kỹ câu hỏi về thời gian.'])->assertSessionHas('status');
        $this->assertEquals(8, $test->fresh()->total_score);
        $this->assertSame('Giỏi', $test->fresh()->level);
        $s->get("/thi-sinh/hoc-tap/{$this->course->id}/lam-bai/{$test->id}/ket-qua")->assertSee('Đọc kỹ câu hỏi về thời gian.')->assertSee('Giáo viên đã chấm lại');
        $s->get("/thi-sinh/lop-hoc/{$c->id}")->assertSee('GV đã chấm lại');
        $s->get('/thi-sinh')->assertSee('Giáo viên vừa chấm lại')->assertSee($a->title);
    }

    public function test_test_mode_limits_attempts_time_and_hides_answers_until_due(): void
    {
        $c = $this->makeClass();
        $c->students()->attach($this->student->id);
        $a = $this->assign($c, ['mode' => 'test', 'show_answers' => 'after_due', 'time_limit_minutes' => 30, 'max_listens' => 2, 'due_at' => now()->addDays(2)->format('Y-m-d H:i')]);
        $this->assertSame(1, $a->max_attempts); // kiểm tra mặc định 1 lượt
        $s = $this->actingAs($this->student, 'student');

        $s->post("/thi-sinh/bai-duoc-giao/{$a->id}/lam-bai");
        $test = UnitTest::sole();
        $this->assertEqualsWithDelta(now()->addMinutes(30)->timestamp, $test->deadline_at->timestamp, 5);
        $s->get("/thi-sinh/hoc-tap/{$this->course->id}/lam-bai/{$test->id}")->assertOk()->assertSee('countdown', false)->assertSee('Được nghe tối đa 2 lần');

        // Vào lại khi đang làm → làm tiếp, không tạo lượt mới
        $s->post("/thi-sinh/bai-duoc-giao/{$a->id}/lam-bai");
        $this->assertSame(1, UnitTest::count());

        $s->post("/thi-sinh/hoc-tap/{$this->course->id}/lam-bai/{$test->id}/nop", ['answers' => [$this->set->id => ['R1' => 'B', 'R2' => 'C']]]);
        $attempt = ExerciseAttempt::sole();

        // Chưa tới hạn nộp → chưa xem đáp án chi tiết
        $s->get("/thi-sinh/hoc-tap/{$this->course->id}/lam-bai/{$test->id}/ket-qua")->assertOk()->assertSee('10')->assertSee('sẽ hiện sau hạn nộp')->assertDontSee('Xem chi tiết');
        $s->get("/thi-sinh/hoc-tap/{$this->course->id}/ket-qua/{$attempt->id}")->assertForbidden();

        // Hết lượt
        $s->post("/thi-sinh/bai-duoc-giao/{$a->id}/lam-bai")->assertSessionHas('status_error', fn ($m) => str_contains($m, 'hết 1 lượt'));

        // Sau hạn nộp → xem được đáp án
        $a->update(['due_at' => now()->subMinute()]);
        $s->get("/thi-sinh/hoc-tap/{$this->course->id}/ket-qua/{$attempt->id}")->assertOk()->assertSee('Giải thích R1');
    }

    public function test_cannot_start_before_open_or_after_due_and_late_submission_scores_zero(): void
    {
        $c = $this->makeClass();
        $c->students()->attach($this->student->id);
        $s = $this->actingAs($this->student, 'student');

        $future = $this->assign($c, ['opens_at' => now()->addDay()->format('Y-m-d H:i')]);
        $s->post("/thi-sinh/bai-duoc-giao/{$future->id}/lam-bai")->assertSessionHas('status_error', fn ($m) => str_contains($m, 'chưa mở'));
        $s->get("/thi-sinh/lop-hoc/{$c->id}")->assertSee('Chưa mở');

        $past = $this->assign($c);
        $past->update(['due_at' => now()->subHour()]);
        $s->post("/thi-sinh/bai-duoc-giao/{$past->id}/lam-bai")->assertSessionHas('status_error', fn ($m) => str_contains($m, 'quá hạn'));

        $timed = $this->assign($c, ['mode' => 'test', 'time_limit_minutes' => 10]);
        $s->post("/thi-sinh/bai-duoc-giao/{$timed->id}/lam-bai");
        $test = UnitTest::where('assignment_id', $timed->id)->sole();
        $test->update(['deadline_at' => now()->subMinutes(10)]); // quá giờ + quá thời gian châm chước
        $s->post("/thi-sinh/hoc-tap/{$this->course->id}/lam-bai/{$test->id}/nop", ['answers' => [$this->set->id => ['R1' => 'B', 'R2' => 'C']]]);
        $this->assertEquals(0, $test->fresh()->total_score);

        // Học viên ngoài lớp không làm được
        $outsider = Student::forceCreate(['last_name' => 'Y', 'email' => 'y@hv.vn', 'password' => bcrypt('x')]);
        $this->actingAs($outsider, 'student')->post("/thi-sinh/bai-duoc-giao/{$timed->id}/lam-bai")->assertSessionHas('status_error');
        $this->actingAs($outsider, 'student')->get("/thi-sinh/lop-hoc/{$c->id}")->assertNotFound();
        $this->actingAs($outsider, 'student')->get("/thi-sinh/hoc-tap/{$this->course->id}/lam-bai/{$test->id}")->assertNotFound();
    }

    public function test_old_recordings_are_pruned_but_scores_kept(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $disk->put('recordings/attempt-1/S1.webm', 'x');
        $disk->put('recordings/tmp/student-1/set-1/S2.webm', 'x');
        touch($disk->path('recordings/tmp/student-1/set-1/S2.webm'), now()->subDays(3)->getTimestamp());
        $old = ExerciseAttempt::forceCreate(['exercise_set_id' => $this->set->id, 'student_id' => $this->student->id, 'answers' => [], 'result' => [], 'files' => ['S1' => ['path' => 'recordings/attempt-1/S1.webm', 'duration' => 20]], 'correct' => 0, 'total' => 1, 'score' => 7, 'status' => 'graded', 'created_at' => now()->subDays(40)]);
        $new = ExerciseAttempt::forceCreate(['exercise_set_id' => $this->set->id, 'student_id' => $this->student->id, 'answers' => [], 'result' => [], 'files' => ['S1' => ['path' => 'recordings/attempt-9/S1.webm', 'duration' => 20]], 'correct' => 0, 'total' => 1, 'score' => 7, 'status' => 'graded']);

        $this->artisan('recordings:prune')->assertSuccessful();

        $this->assertFalse($disk->exists('recordings/attempt-1/S1.webm'));
        $this->assertFalse($disk->exists('recordings/tmp/student-1/set-1/S2.webm'));
        $this->assertNull($old->fresh()->files['S1']['path']);
        $this->assertNotNull($old->fresh()->files['S1']['pruned_at']);
        $this->assertEquals(7, $old->fresh()->score);
        $this->assertSame('recordings/attempt-9/S1.webm', $new->fresh()->files['S1']['path']);

        // ai:work tự chạy dọn tối đa 1 lần / ngày
        $this->assertNotNull(app(RecordingPruner::class)->runDaily());
        $this->assertNull(app(RecordingPruner::class)->runDaily());
    }
}
