<?php

namespace Tests\Feature;

use App\Models\AiJob;
use App\Models\Classroom;
use App\Models\ClassroomCustomExercise;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CustomAttempt;
use App\Models\CustomExercise;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\VideoProgress;
use App\Models\XpLog;
use App\Support\Ai\Jobs\AiJobRunner;
use App\Support\QuestionTypes\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Khoá học → Bài học → Bài tập: bài tập nằm trong bài học, học viên học qua bài tập của bài học. */
class CustomExerciseTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private CourseLesson $lesson;

    private Classroom $c1;

    private Classroom $c2;

    private Teacher $editor;

    private Teacher $classTeacher;

    private Teacher $noPerm;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Registry::flush();
        config(['ai.openai.key' => '']);
        $this->course = Course::create(['language' => 'en', 'title' => 'IELTS', 'is_published' => true]);
        $this->lesson = $this->course->lessons()->create(['title' => 'Bài 1: Family', 'position' => 1]);
        $this->editor = Teacher::create(['name' => 'Cô A', 'email' => 'a@gv.vn', 'password' => 'secret123']);
        $this->classTeacher = Teacher::create(['name' => 'Thầy B', 'email' => 'b@gv.vn', 'password' => 'secret123']);
        $this->noPerm = Teacher::create(['name' => 'Cô C', 'email' => 'c@gv.vn', 'password' => 'secret123']);
        $this->course->editors()->attach($this->editor->id);
        $this->c1 = Classroom::create(['course_id' => $this->course->id, 'name' => 'Lớp 1']);
        $this->c2 = Classroom::create(['course_id' => $this->course->id, 'name' => 'Lớp 2']);
        $this->c1->teachers()->attach($this->editor->id, ['can_customize' => true]);
        $this->c1->teachers()->attach($this->noPerm->id, ['can_customize' => false]);
        $this->c2->teachers()->attach($this->classTeacher->id, ['can_customize' => true]);
        $this->student = Student::forceCreate(['last_name' => 'An', 'email' => 'an@hv.vn', 'password' => bcrypt('x')]);
        $this->c1->students()->attach($this->student->id);
    }

    /** Tạo bài tập trong bài học, thêm phần từ thư viện dạng bài, xuất bản. */
    private function makeExercise(array $types = ['ielts.reading.tfng', 'general.mcq_single'], ?Teacher $by = null, ?int $classroomId = null): CustomExercise
    {
        $t = $this->actingAs($by ?? $this->editor, 'teacher');
        $t->post(route('teacher.custom.store', $this->course), ['title' => 'Mini test 1', 'skill' => 'reading', 'lesson_id' => $this->lesson->id, 'source_classroom_id' => $classroomId])->assertRedirect();
        $ex = CustomExercise::latest('id')->first();
        foreach ($types as $code) {
            $t->post(route('teacher.custom.items.store', [$this->course, $ex]), ['type_code' => $code])->assertRedirect();
        }
        $t->put(route('teacher.custom.update', [$this->course, $ex]), ['title' => 'Mini test 1', 'skill' => 'reading', 'lesson_id' => $this->lesson->id, 'status' => 'published'])->assertSessionHasNoErrors();

        return $ex->fresh('items');
    }

    private function start(CustomExercise $ex, ?Student $s = null): CustomAttempt
    {
        $this->actingAs($s ?? $this->student, 'student')->post(route('portal.custom.start-exercise', [$this->c1, $ex]))->assertRedirect();

        return CustomAttempt::latest('id')->first();
    }

    public function test_exercises_live_inside_lessons_and_validate_content(): void
    {
        $ex = $this->makeExercise();
        $this->assertSame($this->lesson->id, $ex->lesson_id);
        $this->assertNull($ex->source_classroom_id);
        $this->assertCount(2, $ex->items);
        $this->assertSame(Registry::get('ielts.reading.tfng')->sample, $ex->items[0]->content);

        // Không có bài học → không tạo được
        $this->actingAs($this->editor, 'teacher')->post(route('teacher.custom.store', $this->course), ['title' => 'X', 'skill' => 'mixed'])->assertSessionHasErrors('lesson_id');

        $item = $ex->items[1];
        $this->actingAs($this->editor, 'teacher')->put(route('teacher.custom.items.update', [$this->course, $ex, $item]), ['content' => json_encode(['questions' => [['id' => '1', 'prompt' => 'X', 'options' => ['a', 'b'], 'answer' => 'Z']]])])
            ->assertSessionHasErrors('item_'.$item->id);
        $this->actingAs($this->editor, 'teacher')->put(route('teacher.custom.items.update', [$this->course, $ex, $item]), ['content' => json_encode(['questions' => [['id' => '1', 'prompt' => '2 + 2 = ?', 'options' => ['3', '4', '5'], 'answer' => 'B']]]), 'points' => 2])->assertSessionHasNoErrors();
        $this->assertEquals(['points' => 2], $item->fresh()->scoring);

        // Trang bài học hiện bài tập; có nút soạn / AI dựng
        $this->actingAs($this->editor, 'teacher')->get(route('teacher.lessons.edit', [$this->course, $this->lesson]))->assertOk()
            ->assertSee('Bài tập của bài học')->assertSee('Mini test 1')->assertSee('Tải file bài tập cho AI dựng');
        $this->actingAs($this->editor, 'teacher')->get(route('teacher.lessons.index', $this->course))->assertOk()->assertSee('1 bài tập');
        $this->actingAs($this->editor, 'teacher')->get(route('teacher.custom.edit', [$this->course, $ex]))->assertOk()->assertSee('2 + 2 = ?')->assertSee('Bài học “Bài 1: Family”', false);
        // Trình soạn dạng form (không cần sửa JSON): mỗi phần có form theo dạng bài, JSON chỉ ở mục Nâng cao
        $this->actingAs($this->editor, 'teacher')->get(route('teacher.custom.edit', [$this->course, $ex]))
            ->assertSee('class="qte" data-engine="choice"', false)->assertSee('data-qte-json', false)->assertSee('Nâng cao: sửa JSON trực tiếp')->assertSee('js/qt-editor.js', false);
        $this->actingAs($this->editor, 'teacher')->post(route('teacher.custom.items.store', [$this->course, $ex]), ['type_code' => 'hsk.word_order'])->assertSessionHasErrors('type_code');

        // Xoá bài học → xoá luôn bài tập của nó
        $this->lesson->delete();
        $this->assertSame(0, CustomExercise::count());
    }

    public function test_class_teacher_adds_class_only_exercise_and_cannot_touch_course_exercises(): void
    {
        $ex = $this->makeExercise();

        // Chỉ có quyền tuỳ chỉnh ở lớp 2: không tạo bài chung của khoá, không sửa bài của khoá, không vào trang soạn bài học
        $this->actingAs($this->classTeacher, 'teacher')->post(route('teacher.custom.store', $this->course), ['title' => 'X', 'skill' => 'mixed', 'lesson_id' => $this->lesson->id])->assertForbidden();
        $this->actingAs($this->classTeacher, 'teacher')->put(route('teacher.custom.items.update', [$this->course, $ex, $ex->items[0]]), ['content' => '{}'])->assertForbidden();
        $this->actingAs($this->classTeacher, 'teacher')->get(route('teacher.lessons.index', $this->course))->assertForbidden();

        // …nhưng tạo được bài riêng cho lớp 2 trong bài học
        $own = $this->makeExercise(['general.mcq_single'], $this->classTeacher, $this->c2->id);
        $this->assertSame($this->c2->id, $own->source_classroom_id);
        $this->actingAs($this->classTeacher, 'teacher')->post(route('teacher.custom.store', $this->course), ['title' => 'X', 'skill' => 'mixed', 'lesson_id' => $this->lesson->id, 'source_classroom_id' => $this->c1->id])->assertForbidden();

        // Trang lớp: bài học + bài tập; nút "bài tập riêng" chỉ khi có quyền
        $this->actingAs($this->classTeacher, 'teacher')->get(route('teacher.classrooms.show', $this->c2))->assertOk()
            ->assertSee('Bài 1: Family')->assertSee('Mini test 1')->assertSee('riêng của lớp')->assertSee('+ Bài tập riêng cho lớp');
        $this->actingAs($this->noPerm, 'teacher')->get(route('teacher.classrooms.show', $this->c1))->assertOk()->assertDontSee('+ Bài tập riêng cho lớp');

        // Học viên lớp 1 thấy bài chung, không làm được bài riêng của lớp 2
        $this->actingAs($this->student, 'student')->get(route('portal.lessons.show', [$this->c1, $this->lesson]))->assertOk()->assertSee('Mini test 1')->assertSee('Làm bài');
        $this->actingAs($this->student, 'student')->post(route('portal.custom.start-exercise', [$this->c1, $own]))->assertNotFound();
        $this->actingAs($this->student, 'student')->get(route('portal.classes.show', $this->c1))->assertSee('1 bài tập');

        // Quản trị viên ẩn bài, đánh dấu bài chuẩn
        User::forceCreate(['id' => 1, 'name' => 'Owner', 'email' => 'admin@gmail.com', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'boss@x.x', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $this->actingAs($admin)->get(route('admin.courses.lessons.edit', [$this->course, $this->lesson]))->assertOk()->assertSee('Mini test 1');
        $this->actingAs($admin)->put(route('admin.courses.custom.update', [$this->course, $ex]), ['title' => 'Mini test 1', 'skill' => 'reading', 'lesson_id' => $this->lesson->id, 'status' => 'hidden', 'is_standard' => 1]);
        $this->assertTrue($ex->fresh()->is_standard);
        $this->actingAs($this->student, 'student')->get(route('portal.lessons.show', [$this->c1, $this->lesson]))->assertDontSee('Mini test 1');
    }

    public function test_student_learns_through_lesson_exercise_auto_graded_with_xp(): void
    {
        $ex = $this->makeExercise();
        $attempt = $this->start($ex);
        $this->assertSame(1, ClassroomCustomExercise::count(), 'Cài đặt của lớp cho bài được tạo khi học viên bắt đầu');
        $this->actingAs($this->student, 'student')->get(route('portal.custom.do', $attempt))->assertOk()->assertSee('Nộp bài')->assertDontSee('&quot;answer&quot;', false);

        [$tfng, $mcq] = $ex->items;
        $answers = [$tfng->id => ['1' => 'FALSE', '2' => 'NOT GIVEN', '3' => 'FALSE'], $mcq->id => ['1' => 'B', '2' => 'B']];
        $this->actingAs($this->student, 'student')->post(route('portal.custom.submit', $attempt), ['answers' => json_encode($answers)])->assertRedirect(route('portal.custom.result', $attempt));
        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertEquals(8.0, $attempt->score);
        $this->assertGreaterThan(0, XpLog::where('student_id', $this->student->id)->sum('points'));
        $this->actingAs($this->student, 'student')->get(route('portal.lessons.show', [$this->c1, $this->lesson]))->assertSee('8.0/10')->assertSee('Làm lại');

        // Bảng điểm lớp (mở từ bài tập); bài đã có người làm thì khoá nội dung
        $this->actingAs($this->editor, 'teacher')->get(route('teacher.classrooms.custom.open', [$this->c1, $ex]))->assertRedirect();
        $cce = ClassroomCustomExercise::sole();
        $this->actingAs($this->editor, 'teacher')->get(route('teacher.classrooms.custom.results', [$this->c1, $cce]))->assertOk()->assertSee('An')->assertSee('8.0');
        // Bài đã có người làm vẫn sửa được; điểm bài đã nộp giữ nguyên, "Chấm lại" mới tính theo đáp án mới
        $attempt = \App\Models\CustomAttempt::where('status', 'graded')->first();
        $before = (float) $attempt->score;
        $this->actingAs($this->editor, 'teacher')->get(route('teacher.custom.edit', [$this->course, $ex]))->assertOk()->assertSee('Chấm lại bài đã nộp');
        $this->actingAs($this->editor, 'teacher')->put(route('teacher.custom.items.update', [$this->course, $ex, $tfng]), ['content' => json_encode($tfng->content)])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals($before, (float) $attempt->fresh()->score, 'Sửa bài không đổi điểm cũ');
        $this->actingAs($this->editor, 'teacher')->post(route('teacher.custom.regrade', [$this->course, $ex]))->assertSessionHas('status', fn ($m) => str_contains($m, 'Đã chấm lại'));
        $this->assertEquals($before, (float) $attempt->fresh()->score, 'Đáp án không đổi → chấm lại vẫn ra điểm cũ');

        // Giới hạn lượt (giáo viên đặt ở bảng điểm)
        $this->actingAs($this->editor, 'teacher')->put(route('teacher.classrooms.custom.update', [$this->c1, $cce]), ['max_attempts' => 1, 'show_answers' => 'after_submit'])->assertSessionHasNoErrors();
        $this->actingAs($this->student, 'student')->post(route('portal.custom.start-exercise', [$this->c1, $ex]))->assertSessionHas('status_error', fn ($m) => str_contains($m, 'hết 1 lượt'));
    }

    public function test_time_limit_late_submission_and_video_lock(): void
    {
        $ex = $this->makeExercise(['general.mcq_single']);
        $this->lesson->update(['require_video' => true]);
        $video = $this->lesson->videos()->create(['title' => 'V', 'provider' => 'local', 'path' => 'x.mp4', 'duration_seconds' => 60]);
        ClassroomCustomExercise::create(['classroom_id' => $this->c1->id, 'custom_exercise_id' => $ex->id, 'lesson_id' => $this->lesson->id, 'time_limit_minutes' => 10]);

        $this->actingAs($this->student, 'student')->post(route('portal.custom.start-exercise', [$this->c1, $ex]))->assertSessionHas('status_error', fn ($m) => str_contains($m, 'xem xong video'));
        VideoProgress::create(['lesson_video_id' => $video->id, 'student_id' => $this->student->id, 'watched_seconds' => 60, 'percent' => 100, 'completed_at' => now()]);
        $attempt = $this->start($ex);
        $this->assertNotNull($attempt->deadline_at);

        $this->travel(13)->minutes();
        $this->actingAs($this->student, 'student')->post(route('portal.custom.submit', $attempt), ['answers' => json_encode([$ex->items[0]->id => ['1' => 'B', '2' => 'B']])]);
        $this->assertEquals(0, $attempt->fresh()->score, 'Nộp trễ quá giờ → 0 điểm');
    }

    public function test_writing_item_graded_by_ai_or_teacher(): void
    {
        $ex = $this->makeExercise(['general.mcq_single', 'general.paragraph']);
        [$mcq, $writing] = $ex->items;
        config(['ai.openai.key' => 'sk-test-123456789012345678', 'ai.limits' => ['per_actor_daily' => 0, 'monthly_calls' => 0, 'monthly_budget_usd' => 0]]);
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
            'criteria' => [['name' => 'Hoàn thành yêu cầu', 'score' => 8, 'comment' => 'Tốt'], ['name' => 'Mạch lạc, liên kết', 'score' => 6, 'comment' => 'Thiếu từ nối'], ['name' => 'Từ vựng', 'score' => 7, 'comment' => 'Ổn'], ['name' => 'Ngữ pháp', 'score' => 7, 'comment' => 'Vài lỗi thì']],
            'overall_comment' => 'Bài khá.',
        ])]]]], 'usage' => ['input_tokens' => 500, 'output_tokens' => 200]])]);

        $attempt = $this->start($ex);
        $this->actingAs($this->student, 'student')->post(route('portal.custom.submit', $attempt), ['answers' => json_encode([
            $mcq->id => ['1' => 'B', '2' => 'B'], $writing->id => ['text' => str_repeat('My best friend is kind and funny. ', 12)],
        ])]);
        $this->assertSame('grading', $attempt->fresh()->status);
        $this->assertSame(1, AiJob::where('type', 'custom_grade')->count());

        app(AiJobRunner::class)->work(30);
        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertEquals(4.8, $attempt->earned);
        $this->assertEquals(8.0, $attempt->score);
        $this->actingAs($this->student, 'student')->get(route('portal.custom.result', $attempt))->assertSee('Thiếu từ nối');

        $cce = ClassroomCustomExercise::sole();
        $this->actingAs($this->editor, 'teacher')->put(route('teacher.classrooms.custom.review.save', [$this->c1, $cce, $attempt]), [
            'items' => [$writing->id => ['score' => 10, 'comment' => 'Rất tốt']], 'teacher_comment' => 'Cố gắng tốt',
        ])->assertSessionHasNoErrors();
        $this->assertEquals(10.0, $attempt->fresh()->score);
        $this->actingAs($this->editor, 'teacher')->put(route('teacher.classrooms.custom.review.save', [$this->c1, $cce, $attempt]), ['teacher_score' => 9.5]);
        $this->assertSame(9.5, $attempt->fresh()->finalScore());
    }

    public function test_without_ai_writing_waits_for_teacher(): void
    {
        $ex = $this->makeExercise(['general.paragraph']);
        $attempt = $this->start($ex);
        $this->actingAs($this->student, 'student')->post(route('portal.custom.submit', $attempt), ['answers' => json_encode([$ex->items[0]->id => ['text' => 'Hello world']])]);
        $this->assertSame('grading', $attempt->fresh()->status);
        $this->assertSame(0, AiJob::count());
        $this->actingAs($this->editor, 'teacher')->put(route('teacher.classrooms.custom.review.save', [$this->c1, ClassroomCustomExercise::sole(), $attempt]), ['items' => [$ex->items[0]->id => ['score' => 6]]]);
        $this->assertEquals(6.0, $attempt->fresh()->score);
    }

    public function test_duplicate_keeps_lesson_and_original_untouched(): void
    {
        $ex = $this->makeExercise();
        $this->actingAs($this->editor, 'teacher')->post(route('teacher.custom.duplicate', [$this->course, $ex]))->assertRedirect();
        $copy = CustomExercise::latest('id')->first();
        $this->assertSame($this->lesson->id, $copy->lesson_id);
        $this->assertSame($ex->id, $copy->parent_id);
        $this->assertSame('draft', $copy->status);
        $this->assertCount(2, $copy->items);
    }
}
