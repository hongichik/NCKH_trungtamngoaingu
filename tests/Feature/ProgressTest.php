<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\ExerciseSet;
use App\Models\PracticeSet;
use App\Models\Student;
use App\Models\StudentStat;
use App\Models\Teacher;
use App\Models\TextbookUnit;
use App\Models\UnitProfile;
use App\Models\UnitTest;
use App\Models\XpLog;
use App\Support\Ai\Jobs\AiJobRunner;
use App\Support\Progress\Gamification;
use App\Support\Vocab\ChineseDrill;
use App\Support\Vocab\LevelLists;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** GĐ6 (danh sách từ HSK/CEFR, luyện tiếng Trung) + GĐ7 (XP, chuỗi ngày, huy hiệu, sổ lỗi sai, bài luyện AI, đề thi thử, bản đồ năng lực). */
class ProgressTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $teacher;

    private Course $course;

    private TextbookUnit $unit;

    private TextbookUnit $unit2;

    private ExerciseSet $set;

    private Student $student;

    private Classroom $class;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.openai.key' => 'sk-test-123456789012345678', 'ai.limits' => ['per_actor_daily' => 0, 'monthly_calls' => 0, 'monthly_budget_usd' => 0]]);
        $this->teacher = Teacher::create(['name' => 'Cô Lan', 'email' => 'lan@gv.vn', 'password' => 'secret123']);
        $book = $this->teacher->textbooks()->create(['title' => 'English 1', 'language' => 'en', 'level' => 'A2', 'source_type' => 'pdf', 'original_name' => 'a.pdf', 'status' => 'ready', 'page_count' => 10]);
        $this->unit = $book->units()->create(['position' => 1, 'title' => 'Unit 1: Friends', 'start_page' => 1, 'end_page' => 5, 'status' => 'ready']);
        $this->unit2 = $book->units()->create(['position' => 2, 'title' => 'Unit 2: School', 'start_page' => 6, 'end_page' => 9, 'status' => 'ready']);
        UnitProfile::create(['unit_id' => $this->unit->id, 'data' => ['vocabulary' => [['word' => 'friend', 'meaning_vi' => 'bạn'], ['word' => 'negotiation', 'meaning_vi' => 'đàm phán']], 'grammar_points' => [], 'passages' => [], 'dialogues' => []]]);
        UnitProfile::create(['unit_id' => $this->unit2->id, 'data' => ['vocabulary' => [], 'grammar_points' => [], 'passages' => [], 'dialogues' => []]]);
        $this->course = Course::create(['teacher_id' => $this->teacher->id, 'textbook_id' => $book->id, 'language' => 'en', 'title' => 'English 1', 'is_published' => true]);
        $this->set = $this->makeSet($this->unit, 'reading');
        $this->makeSet($this->unit2, 'reading');
        $this->student = Student::forceCreate(['last_name' => 'Nguyễn An', 'email' => 'an@hv.vn', 'password' => bcrypt('x')]);
        $this->class = Classroom::create(['teacher_id' => $this->teacher->id, 'course_id' => $this->course->id, 'name' => 'Lớp A2']);
        $this->class->students()->attach($this->student->id);
    }

    private function makeSet(TextbookUnit $unit, string $skill): ExerciseSet
    {
        $set = $unit->exerciseSets()->create(['skill' => $skill, 'title' => 'Đọc '.$unit->position, 'status' => 'published', 'published_at' => now(), 'source' => 'textbook', 'teacher_id' => $this->teacher->id, 'matrix' => [], 'data' => ['passage' => 'Tom has two friends. They play football every day after school.']]);
        foreach ([['R1', 'B', 'How many friends?'], ['R2', 'C', 'When do they play?']] as $i => [$code, $ans, $prompt]) {
            $set->questions()->create(['position' => $i + 1, 'code' => $code, 'type' => 'mcq', 'prompt' => $prompt, 'options' => ['A. one', 'B. two', 'C. after school', 'D. never'], 'answer' => $ans, 'explanation' => "Giải thích {$code}", 'evidence' => 'Tom has two friends.']);
        }

        return $set;
    }

    private function doAssignment(array $answers, array $extra = []): UnitTest
    {
        $a = Assignment::create($extra + ['classroom_id' => $this->class->id, 'unit_id' => $this->unit->id, 'title' => 'Bài Unit 1', 'mode' => 'practice', 'sections' => [['skill' => 'reading', 'set_id' => $this->set->id]], 'show_answers' => 'after_submit']);
        $s = $this->actingAs($this->student, 'student');
        $s->post("/thi-sinh/bai-duoc-giao/{$a->id}/lam-bai")->assertRedirect();
        $test = UnitTest::where('assignment_id', $a->id)->latest('id')->first();
        $s->post("/thi-sinh/hoc-tap/{$this->course->id}/lam-bai/{$test->id}/nop", ['answers' => [$this->set->id => $answers]])->assertRedirect();

        return $test->fresh();
    }

    public function test_level_lists_and_pinyin_checks(): void
    {
        $this->assertSame('ok', LevelLists::checkPinyin('朋友', 'péngyou'));
        $this->assertSame('tone', LevelLists::checkPinyin('朋友', 'pēngyǒu'));
        $this->assertSame('mismatch', LevelLists::checkPinyin('朋友', 'lǎoshī'));
        $this->assertSame(1, LevelLists::hskLevel('朋友'));
        $this->assertSame(1, LevelLists::cefrLevel('friends'));
        $this->assertSame(3, LevelLists::courseLevel('HSK 3', 'zh'));
        $this->assertSame(2, LevelLists::courseLevel('A2+', 'en'));
        $this->assertArrayHasKey('negotiation', LevelLists::aboveLevel('The negotiation was long.', 'en', 2));
        $this->assertArrayHasKey('经济', LevelLists::aboveLevel('我喜欢经济。', 'zh', 2));

        // Giáo viên thấy cấp của từ + từ vượt trình độ trong đề
        $this->set->questions()->first()->update(['prompt' => 'Why was the negotiation about the environment difficult?']);
        $t = $this->actingAs($this->teacher, 'teacher');
    }

    public function test_chinese_drill_and_xp_rewards(): void
    {
        $qs = app(ChineseDrill::class)->build([
            ['word' => '朋友', 'pinyin' => 'péngyou', 'meaning' => 'bạn'], ['word' => '学校', 'pinyin' => 'xuéxiào', 'meaning' => 'trường'],
            ['word' => '老师', 'pinyin' => 'lǎoshī', 'meaning' => 'thầy cô'], ['word' => '你好', 'pinyin' => 'nǐ hǎo', 'meaning' => 'xin chào'],
        ], 10);
        $this->assertNotEmpty($qs);
        $this->assertEqualsCanonicalizing(['tone', 'listen', 'hanzi', 'radical', 'dictation'], array_values(array_unique(array_column($qs, 'type'))));
        foreach ($qs as $q) {
            if (isset($q['options'])) {
                $this->assertContains($q['answer'], $q['options']);
                $this->assertSame(count($q['options']), count(array_unique($q['options'])));
            }
        }

        // Unit tiếng Trung được giao → trang luyện + nộp kết quả nhận XP + huy hiệu
        $book = $this->teacher->textbooks()->create(['title' => 'HSK 1', 'language' => 'zh', 'level' => 'HSK 1', 'source_type' => 'pdf', 'original_name' => 'z.pdf', 'status' => 'ready', 'page_count' => 5]);
        $zu = $book->units()->create(['position' => 1, 'title' => 'Bài 1', 'start_page' => 1, 'end_page' => 2, 'status' => 'ready']);
        UnitProfile::create(['unit_id' => $zu->id, 'data' => ['vocabulary' => [['word' => '朋友', 'pinyin' => 'péngyou', 'meaning_vi' => 'bạn'], ['word' => '学校', 'pinyin' => 'xuéxiào', 'meaning_vi' => 'trường'], ['word' => '老师', 'pinyin' => 'lǎoshī', 'meaning_vi' => 'thầy cô']]]]);
        $zc = Course::create(['teacher_id' => $this->teacher->id, 'textbook_id' => $book->id, 'language' => 'zh', 'title' => 'HSK 1', 'is_published' => true]);
        $cls = Classroom::create(['teacher_id' => $this->teacher->id, 'course_id' => $zc->id, 'name' => 'Lớp HSK']);
        $cls->students()->attach($this->student->id);
        Assignment::create(['classroom_id' => $cls->id, 'unit_id' => $zu->id, 'title' => 'Bài 1', 'mode' => 'practice', 'sections' => [], 'show_answers' => 'after_submit']);

        $s = $this->actingAs($this->student, 'student');
        $s->get("/thi-sinh/on-tu-vung/luyen-tieng-trung/{$zu->id}")->assertOk()->assertSee('Luyện tiếng Trung')->assertSee('péngyou');
        $s->get("/thi-sinh/on-tu-vung/luyen-tieng-trung/{$this->unit->id}")->assertNotFound(); // Unit tiếng Anh
        $r = $s->postJson('/thi-sinh/on-tu-vung/ket-qua-luyen', ['kind' => 'drill', 'unit_id' => $zu->id, 'correct' => 8, 'total' => 8])->assertOk()->json();
        $this->assertSame(16, $r['xp']);
        $this->assertContains('Không sai câu nào', $r['badges']);
        // Trần XP mỗi ngày của bài luyện = 100
        foreach (range(1, 10) as $_) {
            $s->postJson('/thi-sinh/on-tu-vung/ket-qua-luyen', ['kind' => 'drill', 'unit_id' => $zu->id, 'correct' => 8, 'total' => 8]);
        }
        $this->assertSame(100, (int) XpLog::where('source', 'drill')->sum('points'));
        $this->assertSame(100, StudentStat::sole()->xp);
        $this->assertTrue($this->student->badges()->where('code', 'xp_100')->exists());
    }

    public function test_streak_goal_and_test_completion_rewards(): void
    {
        $g = app(Gamification::class);
        StudentStat::create(['student_id' => $this->student->id, 'xp' => 0, 'streak' => 2, 'best_streak' => 2, 'last_active_on' => today()->subDay()]);
        $g->award($this->student, 'vocab', 2);
        $stat = StudentStat::sole();
        $this->assertSame(3, $stat->streak);
        $this->assertTrue($this->student->badges()->where('code', 'streak_3')->exists());
        $g->award($this->student, 'vocab', 2); // cùng ngày → chuỗi giữ nguyên
        $this->assertSame(3, StudentStat::sole()->streak);

        // Bỏ học 2 ngày → chuỗi hiển thị về 0, học lại bắt đầu từ 1
        StudentStat::sole()->update(['last_active_on' => today()->subDays(3)]);
        $this->assertSame(0, $g->currentStreak(StudentStat::sole()));
        $g->award($this->student, 'mistake', 2);
        $this->assertSame(1, StudentStat::sole()->streak);
        $this->assertSame(3, StudentStat::sole()->best_streak);

        // Làm bài được giao (10 điểm) → XP + huy hiệu
        $test = $this->doAssignment(['R1' => 'B', 'R2' => 'C']);
        $this->assertSame('completed', $test->status);
        $this->assertSame(60, (int) XpLog::where('source', 'test')->sum('points')); // 10 + 10×5
        foreach (['first_test', 'unit_master', 'excellent'] as $b) {
            $this->assertTrue($this->student->badges()->where('code', $b)->exists(), $b);
        }

        // Trang tiến độ + đặt mục tiêu + báo cáo
        $s = $this->actingAs($this->student, 'student');
        $s->post('/thi-sinh/tien-do/muc-tieu', ['daily_goal' => 50])->assertSessionHas('status');
        $this->assertSame(50, StudentStat::sole()->daily_goal);
        $s->get('/thi-sinh/tien-do')->assertOk()->assertSee('Chuỗi ngày học')->assertSee('Khởi động')->assertSee('Unit 1: Friends')->assertSee('/50 XP', false);
        $s->get('/thi-sinh/tien-do/bao-cao')->assertOk()->assertSee('Báo cáo học tập')->assertSee('In / Lưu PDF')->assertSee('Kỹ năng mạnh nhất');
        $s->get('/thi-sinh')->assertSee('ngày liên tiếp')->assertSee('XP');
    }

    public function test_mistake_book_retry_and_ai_practice(): void
    {
        $this->doAssignment(['R1' => 'B', 'R2' => 'A']); // sai R2
        $s = $this->actingAs($this->student, 'student');
        $s->get('/thi-sinh/so-loi-sai')->assertOk()->assertSee('When do they play?')->assertDontSee('How many friends?')->assertSee('Xem lại bài đọc');

        // Làm lại sai → vẫn còn; làm đúng → đã sửa, biến mất, +XP
        $key = $this->set->id.':R2';
        $s->post('/thi-sinh/so-loi-sai/lam-lai', ['answers' => [$key => 'D']])->assertRedirect();
        $s->get('/thi-sinh/so-loi-sai')->assertSee('When do they play?');
        $s->post('/thi-sinh/so-loi-sai/lam-lai', ['answers' => [$key => 'C']])->assertSessionHas('status', fn ($m) => str_contains($m, 'đúng 1/1'));
        $s->get('/thi-sinh/so-loi-sai')->assertSee('Không còn lỗi sai');
        $this->assertSame(2, (int) XpLog::where('source', 'mistake')->sum('points'));

        // Sai lại ở lần làm mới → hiện lại; đáp án ẩn tới hạn nộp → chưa vào sổ
        $this->travel(1)->minutes();
        $this->doAssignment(['R1' => 'A', 'R2' => 'B'], ['show_answers' => 'after_due', 'due_at' => now()->addDay()]);
        $s->get('/thi-sinh/so-loi-sai')->assertSee('Không còn lỗi sai');
        $this->doAssignment(['R1' => 'A', 'R2' => 'C']);
        $s->get('/thi-sinh/so-loi-sai')->assertSee('How many friends?');

        // AI soạn bài luyện tương tự
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['questions' => [
            ['type' => 'mcq', 'prompt' => 'How many cats does Mai have? (She has three cats.)', 'options' => ['A. two', 'B. three', 'C. four', 'D. five'], 'answer' => 'B', 'explanation_vi' => 'Đọc kỹ số lượng.', 'focus_vi' => 'Số lượng'],
            ['type' => 'true_false', 'prompt' => 'Three is more than two.', 'options' => [], 'answer' => 'true', 'explanation_vi' => '3 > 2', 'focus_vi' => 'So sánh'],
            ['type' => 'gap_fill', 'prompt' => 'I have two ____ (bạn).', 'options' => [], 'answer' => 'friends', 'explanation_vi' => 'Danh từ số nhiều', 'focus_vi' => 'Từ vựng'],
            ['type' => 'mcq', 'prompt' => 'Bad question', 'options' => ['A. x', 'B. y'], 'answer' => 'D', 'explanation_vi' => '', 'focus_vi' => ''],
        ]])]]]], 'usage' => ['input_tokens' => 500, 'output_tokens' => 300]])]);
        $s->post('/thi-sinh/so-loi-sai/bai-luyen')->assertRedirect();
        $p = PracticeSet::sole();
        $s->get("/thi-sinh/bai-luyen/{$p->id}")->assertSee('AI đang soạn');
        app(AiJobRunner::class)->work(30);
        $p->refresh();
        $this->assertSame('ready', $p->status);
        $this->assertCount(3, $p->questions); // câu đáp án ngoài lựa chọn bị loại
        $html = $s->get("/thi-sinh/bai-luyen/{$p->id}")->assertOk()->assertSee('How many cats')->getContent();
        $this->assertStringNotContainsString('Đọc kỹ số lượng', $html); // giải thích chỉ hiện sau khi nộp
        $s->post("/thi-sinh/bai-luyen/{$p->id}", ['answers' => ['P1' => 'B', 'P2' => 'True', 'P3' => 'friend']])->assertRedirect();
        $p->refresh();
        $this->assertSame('done', $p->status);
        $this->assertEquals(6.7, $p->score);
        $s->get("/thi-sinh/bai-luyen/{$p->id}")->assertSee('Đọc kỹ số lượng')->assertSee('friends');

        // Người khác không xem được
        $other = Student::forceCreate(['last_name' => 'B', 'email' => 'b@hv.vn', 'password' => bcrypt('x')]);
        $this->actingAs($other, 'student')->get("/thi-sinh/bai-luyen/{$p->id}")->assertNotFound();
    }

    public function test_mock_exam_competency_map_and_leaderboard(): void
    {
        $t = $this->actingAs($this->teacher, 'teacher');
        $t->get("/giao-vien/lop-hoc/{$this->class->id}/de-thi-thu")->assertOk()->assertSee('Unit 1: Friends')->assertSee('Unit 2: School');
        $t->post("/giao-vien/lop-hoc/{$this->class->id}/de-thi-thu", [
            'title' => 'Thi thử giữa kỳ', 'unit_ids' => [$this->unit->id, $this->unit2->id], 'counts' => ['reading' => 2, 'listening' => 1],
            'time_limit_minutes' => 45, 'show_answers' => 'after_submit',
        ])->assertSessionHas('status', fn ($m) => str_contains($m, '2 phần') && str_contains($m, 'Nghe'));
        $mock = Assignment::where('kind', 'mock')->sole();
        $this->assertSame('test', $mock->mode);
        $this->assertCount(2, $mock->sections);
        $this->assertCount(2, array_unique(array_column($mock->sections, 'set_id')));
        $t->get("/giao-vien/lop-hoc/{$this->class->id}")->assertOk();

        // Học viên làm đề thi thử
        $s = $this->actingAs($this->student, 'student');
        $s->get("/thi-sinh/lop-hoc/{$this->class->id}")->assertSee('Thi thử giữa kỳ')->assertSee('Đề thi thử')->assertDontSee('Xem từ vựng');
        $s->post("/thi-sinh/bai-duoc-giao/{$mock->id}/lam-bai")->assertRedirect();
        $test = UnitTest::where('assignment_id', $mock->id)->sole();
        $s->get("/thi-sinh/hoc-tap/{$this->course->id}/lam-bai/{$test->id}")->assertOk()->assertSee('Thi thử giữa kỳ')->assertSee('countdown', false);
        $ids = array_column($mock->sections, 'set_id');
        $s->post("/thi-sinh/hoc-tap/{$this->course->id}/lam-bai/{$test->id}/nop", ['answers' => [$ids[0] => ['R1' => 'B', 'R2' => 'C'], $ids[1] => ['R1' => 'B']]])->assertRedirect();
        $this->assertEquals(7.5, $test->fresh()->total_score);

        // Bài Unit thường → bản đồ năng lực (đề thi thử không tính vào ô Unit)
        $this->doAssignment(['R1' => 'B', 'R2' => 'A']);
        $s->get('/thi-sinh/tien-do')->assertSee('Unit 1: Friends')->assertDontSee('Unit 2: School');
        $t->get("/giao-vien/lop-hoc/{$this->class->id}/nang-luc")->assertOk()->assertSee('Nguyễn An')->assertSee('Unit 1: Friends')->assertSee('XP');

        // Bảng xếp hạng: giáo viên bật mới hiện cho học viên
        $s->get("/thi-sinh/lop-hoc/{$this->class->id}")->assertDontSee('Bảng xếp hạng XP');
        $t->put("/giao-vien/lop-hoc/{$this->class->id}", ['name' => $this->class->name, 'course_id' => $this->course->id, 'allow_join' => 1, 'show_leaderboard' => 1]);
        $this->assertTrue($this->class->fresh()->show_leaderboard);
        $s->get("/thi-sinh/lop-hoc/{$this->class->id}")->assertSee('Bảng xếp hạng XP')->assertSee('Nguyễn An');
        $s->get('/thi-sinh/tien-do')->assertSee('Bảng xếp hạng 7 ngày');
    }
}
