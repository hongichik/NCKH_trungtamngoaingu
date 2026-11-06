<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CustomExercise;
use App\Models\Teacher;
use App\Support\Ai\Jobs\AiJobRunner;
use App\Support\QuestionTypes\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Bài tập đã lưu vẫn dùng AI được cho từng phần: dựng lại nội dung, tạo giọng đọc AI cho file nghe (đổi lại file gốc được). */
class CustomItemAiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://api.openai.com/v1/responses';

    private Course $course;

    private Teacher $editor;

    private CustomExercise $ex;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Registry::flush();
        config(['ai.openai.key' => 'sk-test-123456789012345678', 'ai.tts_enabled' => true, 'ai.limits' => ['per_actor_daily' => 0, 'monthly_calls' => 0, 'monthly_budget_usd' => 0]]);
        $this->course = Course::create(['language' => 'en', 'title' => 'IELTS', 'is_published' => true]);
        $lesson = $this->course->lessons()->create(['title' => 'Bài 1', 'position' => 1]);
        $this->editor = Teacher::create(['name' => 'Cô A', 'email' => 'a@gv.vn', 'password' => 'secret123']);
        $this->course->editors()->attach($this->editor->id);
        $this->ex = CustomExercise::create(['course_id' => $this->course->id, 'lesson_id' => $lesson->id, 'title' => 'Nghe 1', 'skill' => 'listening', 'status' => 'draft', 'source' => 'ai',
            'created_by_type' => 'teacher', 'created_by_id' => $this->editor->id, 'created_by_name' => 'Cô A']);
    }

    private function ai(array $data)
    {
        return Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data)]]]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]]);
    }

    private function mcq(string $q, string $answer = 'B', ?string $transcript = null): array
    {
        return array_filter(['stimulus' => $transcript ? ['transcript' => $transcript] : null,
            'questions' => [['id' => '1', 'prompt' => $q, 'options' => ['Monday', 'Friday', 'Sunday'], 'answer' => $answer]]]);
    }

    public function test_rebuild_saved_item_with_ai_using_audio_transcript_and_teacher_note(): void
    {
        Storage::disk('local')->put('custom-exercises/x/audio/1.mp3', 'ID3real-audio');
        $item = $this->ex->items()->create(['position' => 1, 'title' => 'Part 1', 'type_code' => 'general.mcq_single', 'content' => $this->mcq('Old question?'),
            'audio_path' => 'custom-exercises/x/audio/1.mp3', 'meta' => ['source' => 'PART 1. When is the trip? A. Monday B. Friday C. Sunday']]);
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'The school trip is on Friday.']),
            self::URL => $this->ai(['content_json' => json_encode($this->mcq('When is the trip?')), 'flags' => []]),
        ]);
        $t = $this->actingAs($this->editor, 'teacher');
        $t->get(route('teacher.custom.edit', [$this->course, $this->ex]))->assertOk()->assertSee('Dựng lại phần này bằng AI')->assertDontSee('Tạo giọng đọc AI từ lời bài nghe');

        $t->postJson(route('teacher.custom.items.ai', [$this->course, $this->ex, $item]), ['action' => 'rebuild', 'style' => 'strict', 'note' => 'Thêm câu hỏi về ngày'])
            ->assertOk()->assertJson(['ok' => true, 'pending' => true]);
        $this->assertSame('pending', $item->fresh()->meta['ai']['status']);
        $t->get(route('teacher.custom.edit', [$this->course, $this->ex]))->assertSee('AI đang dựng lại riêng phần này');

        $t->postJson(route('teacher.custom.items.ai-status', [$this->course, $this->ex]))->assertOk()->assertJson(['items' => [$item->id => null]]);
        $item->refresh();
        $this->assertSame('When is the trip?', $item->content['questions'][0]['prompt']);
        $this->assertSame('The school trip is on Friday.', $item->meta['transcript']);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/responses') && str_contains($s = json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'The school trip is on Friday')
            && str_contains($s, 'Thêm câu hỏi về ngày') && str_contains($s, 'PART 1. When is the trip?'));
    }

    public function test_ai_voice_replaces_audio_keeps_original_and_can_revert(): void
    {
        Storage::disk('local')->put('custom-exercises/x/audio/2.mp3', 'ID3teacher-file');
        $item = $this->ex->items()->create(['position' => 1, 'type_code' => 'general.mcq_single', 'content' => $this->mcq('When does the shop close?', 'C', 'The shop closes on Sunday.'),
            'audio_path' => 'custom-exercises/x/audio/2.mp3', 'meta' => []]);
        Http::fake(['https://api.openai.com/v1/audio/speech' => Http::response('ID3ai-voice', 200)]);
        $t = $this->actingAs($this->editor, 'teacher');
        $t->get(route('teacher.custom.edit', [$this->course, $this->ex]))->assertSee('Tạo giọng đọc AI từ lời bài nghe');

        $t->postJson(route('teacher.custom.items.ai', [$this->course, $this->ex, $item]), ['action' => 'tts'])->assertOk()->assertJson(['pending' => true]);
        app(AiJobRunner::class)->work(30);
        $item->refresh();
        $this->assertTrue($item->meta['audio_ai']);
        $this->assertSame('custom-exercises/x/audio/2.mp3', $item->meta['audio_original']);
        $this->assertSame('ID3ai-voice', Storage::disk('local')->get($item->audio_path));
        Http::assertSent(fn ($r) => $r['input'] === 'The shop closes on Sunday.');
        $t->get(route('teacher.custom.edit', [$this->course, $this->ex]))->assertSee('giọng AI')->assertSee('Dùng lại file nghe gốc');

        $t->postJson(route('teacher.custom.items.ai', [$this->course, $this->ex, $item]), ['action' => 'revert'])->assertOk()->assertJson(['pending' => false]);
        $item->refresh();
        $this->assertSame('custom-exercises/x/audio/2.mp3', $item->audio_path);
        $this->assertArrayNotHasKey('audio_ai', $item->meta);

        // Không có lời bài nghe → báo, không gọi AI
        $item->update(['content' => $this->mcq('Q?')]);
        $t->postJson(route('teacher.custom.items.ai', [$this->course, $this->ex, $item]), ['action' => 'tts'])->assertStatus(422);
    }
}
