<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UnitProfile;
use App\Models\VocabReview;
use App\Support\Vocab\Flashcards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VocabTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private Classroom $class;

    private function unit(string $lang, array $vocab, string $title)
    {
        $t = Teacher::firstOrCreate(['email' => 'lan@gv.vn'], ['name' => 'Cô Lan', 'password' => 'secret123']);
        $book = $t->textbooks()->create(['title' => "Sách {$lang}", 'language' => $lang, 'level' => 'A1', 'source_type' => 'pdf', 'original_name' => 'a.pdf', 'status' => 'ready', 'page_count' => 10]);
        $unit = $book->units()->create(['position' => 1, 'title' => $title, 'start_page' => 1, 'end_page' => 5, 'status' => 'ready']);
        UnitProfile::create(['unit_id' => $unit->id, 'data' => ['vocabulary' => $vocab, 'grammar_points' => [], 'passages' => [], 'dialogues' => []]]);
        $course = Course::create(['teacher_id' => $t->id, 'textbook_id' => $book->id, 'language' => $lang, 'title' => "Khoá {$lang}", 'is_published' => true]);

        return [$unit, $course, $t];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = Student::forceCreate(['last_name' => 'An', 'email' => 'an@hv.vn', 'password' => bcrypt('x')]);
    }

    private function assignTo($unit, $course, $teacher): Classroom
    {
        $c = Classroom::create(['teacher_id' => $teacher->id, 'course_id' => $course->id, 'name' => 'Lớp '.$course->language]);
        $c->students()->attach($this->student->id);
        Assignment::create(['classroom_id' => $c->id, 'unit_id' => $unit->id, 'title' => 'Bài '.$unit->title, 'mode' => 'practice', 'sections' => [], 'show_answers' => 'after_submit']);

        return $c;
    }

    public function test_flashcards_only_from_assigned_units_and_sm2_schedule(): void
    {
        [$unit, $course, $t] = $this->unit('en', [
            ['word' => 'friendship', 'pinyin' => '', 'pos' => 'n.', 'meaning_vi' => 'tình bạn', 'example' => 'Friendship matters.'],
            ['word' => 'loyal', 'pos' => 'adj.', 'meaning_vi' => 'trung thành'],
            ['word' => 'Friendship', 'meaning_vi' => 'trùng'],
        ], 'Unit 1: Friends');
        $s = $this->actingAs($this->student, 'student');

        // Chưa được giao → không có thẻ, menu không có
        $s->get('/thi-sinh/on-tu-vung')->assertOk()->assertSee('Chưa có từ vựng để ôn');
        $s->get('/thi-sinh/thong-tin')->assertDontSee('Ôn từ vựng');

        $class = $this->assignTo($unit, $course, $t);
        $s->get('/thi-sinh/thong-tin')->assertSee('Ôn từ vựng');
        $s->get('/thi-sinh/on-tu-vung')->assertOk()->assertSee('Unit 1: Friends')->assertSee('2 từ')->assertDontSee('Tập viết chữ Hán');
        $s->get("/thi-sinh/lop-hoc/{$class->id}")->assertSee('Học thẻ từ');
        $s->get('/thi-sinh/on-tu-vung/hoc')->assertOk()->assertSee('friendship')->assertSee('tình bạn')->assertSee('loyal');

        $key = Flashcards::key('friendship');
        $send = fn ($g) => $s->postJson('/thi-sinh/on-tu-vung/danh-gia', ['unit_id' => $unit->id, 'key' => $key, 'grade' => $g])->assertOk()->json();

        $this->assertSame(1, $send('good')['interval_days']);
        $this->assertSame(3, $send('good')['interval_days']);
        $this->assertSame(8, $send('good')['interval_days']);   // 3 × 2.5
        $r = VocabReview::sole();
        $this->assertSame(3, $r->reps);
        $send('again');
        $r->refresh();
        $this->assertSame(0, $r->reps);
        $this->assertSame(1, $r->lapses);
        $this->assertEquals(2.3, $r->ease);
        $this->assertTrue($r->due_at->lt(now()->addMinutes(11)));

        // Thẻ quên đến hạn lại → có trong lượt ôn, badge menu đếm
        $r->update(['due_at' => now()->subMinute()]);
        $this->assertSame(1, app(Flashcards::class)->dueCount($this->student));
        $cards = app(Flashcards::class)->session($this->student);
        $this->assertSame('friendship', $cards[0]['word']);
        $this->assertFalse($cards[0]['new']);

        // Từ không thuộc Unit được giao / Unit lạ → 404
        $s->postJson('/thi-sinh/on-tu-vung/danh-gia', ['unit_id' => $unit->id, 'key' => str_repeat('a', 40), 'grade' => 'good'])->assertNotFound();
        [$other] = $this->unit('en', [['word' => 'secret', 'meaning_vi' => 'bí mật']], 'Unit 9');
        $s->postJson('/thi-sinh/on-tu-vung/danh-gia', ['unit_id' => $other->id, 'key' => Flashcards::key('secret'), 'grade' => 'good'])->assertNotFound();
        $s->get("/thi-sinh/on-tu-vung/hoc?unit={$other->id}")->assertNotFound();
    }

    public function test_chinese_unit_has_pinyin_and_writing_practice(): void
    {
        [$unit, $course, $t] = $this->unit('zh', [
            ['word' => '朋友', 'pinyin' => 'péngyou', 'pos' => '名词', 'meaning_vi' => 'bạn bè', 'example' => '他是我的朋友。'],
            ['word' => '你好', 'pinyin' => 'nǐ hǎo', 'meaning_vi' => 'xin chào'],
        ], 'Bài 1: 你好');
        $class = $this->assignTo($unit, $course, $t);
        $s = $this->actingAs($this->student, 'student');

        $s->get('/thi-sinh/on-tu-vung')->assertSee('Tập viết chữ Hán');
        $s->get("/thi-sinh/lop-hoc/{$class->id}")->assertSee('Tập viết chữ Hán');
        $html = $s->get("/thi-sinh/on-tu-vung/tap-viet/{$unit->id}")->assertOk()->assertSee('hanzi-writer@3.7.2', false)->getContent();
        foreach (['朋', '友', '你', '好'] as $ch) {
            $this->assertMatchesRegularExpression('/"char":"('.preg_quote($ch, '/').'|'.preg_quote(trim(json_encode($ch), '"'), '/').')"/u', $html);
        }
        $s->get('/thi-sinh/on-tu-vung/hoc')->assertSee('péngyou');
        $s->get("/thi-sinh/hoc-tap/{$course->id}/unit/{$unit->id}/xem/ngu-phap?lop={$class->id}")->assertOk()->assertSee('Hiện pinyin')->assertSee('pinyin-pro@3.26.0', false);

        // Unit tiếng Anh không có trang tập viết
        [$en, $enCourse] = $this->unit('en', [['word' => 'hi', 'meaning_vi' => 'chào']], 'Unit E');
        $this->assignTo($en, $enCourse, $t);
        $s->get("/thi-sinh/on-tu-vung/tap-viet/{$en->id}")->assertNotFound();
    }

    /** Khoá học kiểu mới: từ vựng của bài học (giáo viên / AI lập) → học viên ôn thẻ; bài chưa mở thì chưa có thẻ. */
    public function test_lesson_vocabulary_deck_for_new_course_model(): void
    {
        $teacher = Teacher::create(['name' => 'Cô Hoa', 'email' => 'hoa@gv.vn', 'password' => 'secret123']);
        $course = Course::create(['language' => 'zh', 'title' => 'HSK 1', 'is_published' => true]);
        $course->editors()->attach($teacher->id);
        $l1 = $course->lessons()->create(['title' => '第一课 你好', 'position' => 1, 'is_published' => true]);
        $l2 = $course->lessons()->create(['title' => '第二课', 'position' => 2, 'is_published' => true]);
        $class = Classroom::create(['course_id' => $course->id, 'name' => 'HSK sáng']);
        $class->students()->attach($this->student->id);
        $class->teachers()->attach($teacher->id);

        // Giáo viên nhập danh sách: chữ Hán | pinyin | nghĩa | ví dụ
        $t = $this->actingAs($teacher, 'teacher');
        $t->get(route('teacher.lessons.edit', [$course, $l1]))->assertOk()->assertSee('Từ vựng của bài')->assertSee('AI lập danh sách từ vựng');
        $t->put(route('teacher.lessons.vocabulary', [$course, $l1]), ['vocabulary' => "你好 | nǐ hǎo | xin chào | 你好，老师！\n谢谢 | xièxie | cảm ơn\n\n你好 | trùng"])->assertRedirect();
        $this->assertSame([['word' => '你好', 'pinyin' => 'nǐ hǎo', 'meaning_vi' => 'xin chào', 'example' => '你好，老师！'], ['word' => '谢谢', 'pinyin' => 'xièxie', 'meaning_vi' => 'cảm ơn']], $l1->fresh()->vocabulary);
        $t->get(route('teacher.lessons.edit', [$course, $l1]))->assertSee('你好 | nǐ hǎo | xin chào | 你好，老师！', false);
        // Bài 2 lên lịch mở sau → chưa có thẻ
        $l2->update(['vocabulary' => [['word' => '我', 'pinyin' => 'wǒ', 'meaning_vi' => 'tôi']]]);
        \Illuminate\Support\Facades\DB::table('classroom_lessons')->insert(['classroom_id' => $class->id, 'lesson_id' => $l2->id, 'opens_at' => now()->addDay(), 'hidden' => false]);

        $s = $this->actingAs($this->student, 'student');
        $s->get('/thi-sinh/thong-tin')->assertSee('Ôn từ vựng');
        $s->get('/thi-sinh/on-tu-vung')->assertOk()->assertSee('第一课 你好')->assertSee('2 từ')->assertSee('Tập viết chữ Hán')->assertDontSee('第二课');
        $s->get("/thi-sinh/lop-hoc/{$class->id}/bai-hoc/{$l1->id}")->assertSee('Học thẻ từ của bài')->assertSee('xin chào');
        $s->get('/thi-sinh/on-tu-vung/hoc?deck=l'.$l1->id)->assertOk()->assertSee('谢谢');
        $s->get('/thi-sinh/on-tu-vung/luyen-tieng-trung/l'.$l1->id)->assertOk();
        $s->get('/thi-sinh/on-tu-vung/tap-viet/l'.$l1->id)->assertOk()->assertSee('谢');

        $s->postJson('/thi-sinh/on-tu-vung/danh-gia', ['deck' => 'l'.$l1->id, 'key' => Flashcards::key('你好'), 'grade' => 'good'])->assertOk()->assertJson(['interval_days' => 1]);
        $r = VocabReview::sole();
        $this->assertSame($l1->id, $r->lesson_id);
        $this->assertNull($r->unit_id);
        $s->postJson('/thi-sinh/on-tu-vung/danh-gia', ['deck' => 'l'.$l2->id, 'key' => Flashcards::key('我'), 'grade' => 'good'])->assertNotFound();
        $s->get('/thi-sinh/on-tu-vung/hoc?deck=l'.$l2->id)->assertNotFound();
    }
}
