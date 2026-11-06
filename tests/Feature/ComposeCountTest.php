<?php

namespace Tests\Feature;

use App\Models\AiImport;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CustomExercise;
use App\Models\Teacher;
use App\Support\Ai\Jobs\AiJobRunner;
use App\Support\AiImport\AiImportService;
use App\Support\QuestionTypes\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "AI tự tạo bài tập": số giáo viên nhập = số CÂU HỎI LỚN mỗi kỹ năng. Dù AI lập kế hoạch sai số phần
 * (thiếu, thừa, quên kỹ năng, thêm kỹ năng không yêu cầu), bài tập lưu ra phải có ĐÚNG số câu hỏi lớn đã nhập.
 * AI giả lập trả lời theo nội dung từng yêu cầu (không phụ thuộc thứ tự gửi song song).
 */
class ComposeCountTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://api.openai.com/v1/responses';

    private Course $course;

    private CourseLesson $lesson;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Registry::flush();
        config(['ai.openai.key' => 'sk-test-123456789012345678', 'ai.tts_enabled' => false, 'ai.limits' => ['per_actor_daily' => 0, 'monthly_calls' => 0, 'monthly_budget_usd' => 0]]);
        $this->course = Course::create(['language' => 'en', 'title' => 'English A2', 'is_published' => true]);
        $this->teacher = Teacher::create(['name' => 'Cô Lan', 'email' => 'lan@gv.vn', 'password' => 'secret123']);
        $this->course->editors()->attach($this->teacher->id);
        $this->lesson = $this->course->lessons()->create(['title' => 'Unit 2 — Friends', 'position' => 1, 'summary' => 'Talking about friends']);
    }

    private static function reply(array $data): array
    {
        return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data)]]]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]];
    }

    /** Một mã dạng bài thuộc kỹ năng (theo danh sách dạng bài thật của khoá). */
    private function codeFor(string $skill): string
    {
        return collect(app(AiImportService::class)->menu(new AiImport(['course_id' => $this->course->id])))
            ->pluck('code')->first(fn ($c) => AiImportService::composeSkill($c) === $skill);
    }

    /**
     * AI giả: lượt lập kế hoạch trả $plan (danh sách [kỹ năng, số phần]); lượt dựng trả nội dung mẫu hợp lệ của đúng dạng bài được hỏi.
     *
     * @param  list<array{0: string, 1: int}>  $plan
     * @param  list<string>  $brokenCodes  dạng bài AI luôn dựng hỏng
     */
    private function fakeAi(array $plan, array $brokenCodes = []): void
    {
        $sections = [];
        foreach ($plan as [$skill, $n]) {
            foreach (range(1, $n) as $k) {
                $sections[] = ['title' => "{$skill} {$k}", 'type_code' => $this->codeFor($skill), 'start_quote' => '', 'end_quote' => '', 'audio_file' => null,
                    'needs_audio' => false, 'transcript' => null, 'points_per_question' => null, 'scale' => null, 'confidence' => 0.9, 'origin' => 'new', 'brief' => "Phần {$skill} {$k}"];
            }
        }
        Http::fake([self::URL => function (Request $r) use ($sections, $brokenCodes) {
            if ($r->data()['text']['format']['name'] === 'exercise_plan') {
                return Http::response(self::reply(['title' => 'Friends', 'sections' => $sections]));
            }
            preg_match('/\(mã ([\w.\-]+)\)/u', json_encode($r->data(), JSON_UNESCAPED_UNICODE), $m);
            $type = Registry::get($m[1]);
            $content = in_array($type->code, $brokenCodes, true) ? '{"x":1}' : json_encode($type->sample);

            return Http::response(self::reply(['content_json' => $content, 'flags' => [], 'image_page' => null, 'image_box' => null, 'image_refs' => null]));
        }]);
    }

    /** Gửi yêu cầu qua đúng route giáo viên dùng, chạy hàng đợi, trả về số câu hỏi lớn theo kỹ năng của bài tập đã lưu. */
    private function compose(array $counts): array
    {
        $this->actingAs($this->teacher, 'teacher')
            ->post(route('teacher.lessons.ai-compose', [$this->course, $this->lesson]), ['counts' => $counts + ['reading' => 0, 'listening' => 0, 'speaking' => 0, 'writing' => 0, 'vocab' => 0]])
            ->assertSessionHasNoErrors();
        app(AiJobRunner::class)->work(600);

        $import = AiImport::latest('id')->first();
        $this->assertSame('saved', $import->status, (string) $import->error);
        $ex = CustomExercise::with('items')->findOrFail($import->custom_exercise_id);
        $this->assertSame(1, CustomExercise::where('lesson_id', $this->lesson->id)->count(), 'Mỗi lần tạo = 1 bài tập');

        return $ex->items->countBy(fn ($i) => AiImportService::composeSkill($i->type_code))->sortKeys()->all();
    }

    /** Ca #23 thật: yêu cầu Đọc 10, Nói 15, Nghe 20 — AI chỉ lập Đọc 2, Nói 15, Nghe 2. */
    public function test_ai_plans_too_few_sections_still_returns_exact_counts(): void
    {
        $this->fakeAi([['reading', 2], ['speaking', 15], ['listening', 2]]);
        $this->assertSame(['listening' => 20, 'reading' => 10, 'speaking' => 15], $this->compose(['reading' => 10, 'speaking' => 15, 'listening' => 20]));
    }

    /** Ca #22 thật: yêu cầu Từ vựng 5, Đọc 10, Nói 10, Nghe 10 — AI lập mỗi kỹ năng 1 phần. */
    public function test_ai_plans_one_section_per_skill(): void
    {
        $this->fakeAi([['vocab', 1], ['reading', 1], ['speaking', 1], ['listening', 1]]);
        $this->assertSame(['listening' => 10, 'reading' => 10, 'speaking' => 10, 'vocab' => 5], $this->compose(['vocab' => 5, 'reading' => 10, 'speaking' => 10, 'listening' => 10]));
    }

    public function test_extra_sections_and_unrequested_skills_are_dropped(): void
    {
        $this->fakeAi([['reading', 7], ['listening', 3], ['vocab', 4]]);
        $this->assertSame(['reading' => 3, 'vocab' => 2], $this->compose(['reading' => 3, 'vocab' => 2]));
    }

    public function test_forgotten_skill_is_added(): void
    {
        $this->fakeAi([['reading', 2]]);
        $this->assertSame(['reading' => 2, 'speaking' => 3, 'writing' => 4], $this->compose(['reading' => 2, 'writing' => 4, 'speaking' => 3]));
    }

    public function test_every_skill_at_max_counts(): void
    {
        $this->fakeAi([['reading', 1]]);
        $this->assertSame(['listening' => 8, 'reading' => 8, 'speaking' => 8, 'vocab' => 8, 'writing' => 8],
            $this->compose(['reading' => 8, 'listening' => 8, 'speaking' => 8, 'writing' => 8, 'vocab' => 8]));
    }

    public function test_limits_thirty_per_skill_sixty_total(): void
    {
        $this->fakeAi([['reading', 3], ['listening', 1]]);
        $this->assertSame(['listening' => 30, 'reading' => 30], $this->compose(['reading' => 30, 'listening' => 30]));
        $this->actingAs($this->teacher, 'teacher')->post(route('teacher.lessons.ai-compose', [$this->course, $this->lesson]), ['counts' => ['reading' => 30, 'listening' => 30, 'vocab' => 1]])->assertSessionHasErrors('counts');
        $this->actingAs($this->teacher, 'teacher')->post(route('teacher.lessons.ai-compose', [$this->course, $this->lesson]), ['counts' => ['reading' => 31]])->assertSessionHasErrors('counts.reading');
    }

    /** Phần AI dựng hỏng cả 2 lượt không thể lưu → báo rõ số phần bị bỏ, không im lặng. */
    public function test_broken_sections_are_reported_not_silently_lost(): void
    {
        $this->fakeAi([['reading', 2], ['speaking', 2]], [$this->codeFor('speaking')]);
        $this->actingAs($this->teacher, 'teacher')->post(route('teacher.lessons.ai-compose', [$this->course, $this->lesson]), ['counts' => ['reading' => 2, 'speaking' => 2]]);
        app(AiJobRunner::class)->work(600);
        $import = AiImport::sole();
        $this->assertSame('2 phần AI dựng chưa hợp lệ đã bị bỏ qua.', $import->error);
        $this->assertSame(2, CustomExercise::sole()->items()->count());
    }
}
