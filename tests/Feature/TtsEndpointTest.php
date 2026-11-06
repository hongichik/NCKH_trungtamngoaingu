<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\TtsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Giọng đọc AI cho các nút "Nghe" / loa: GET /tts → mp3, lưu đệm theo nội dung (đoạn đã tạo dùng lại mãi). */
class TtsEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['ai.openai.key' => 'sk-test-123456789012345678', 'ai.tts_enabled' => true, 'ai.limits' => ['per_actor_daily' => 0, 'monthly_calls' => 0, 'monthly_budget_usd' => 0]]);
    }

    private function url(string $text, string $lang = 'en', string $g = 'female'): string
    {
        return '/tts?lang='.$lang.'&g='.$g.'&t='.urlencode(base64_encode($text));
    }

    public function test_guest_cannot_generate_speech(): void
    {
        Http::fake();
        $this->get($this->url('Hello'))->assertStatus(401);
        Http::assertNothingSent();
    }

    public function test_generates_once_then_reuses_cache(): void
    {
        Http::fake(['https://api.openai.com/v1/audio/speech' => Http::response('ID3mp3-bytes', 200)]);
        $student = Student::forceCreate(['last_name' => 'An', 'email' => 'an@x.vn', 'password' => bcrypt('x')]);
        $s = $this->actingAs($student, 'student');

        $s->get($this->url('The shop closes on Sunday.'))->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $s->get($this->url('The shop closes on Sunday.'))->assertOk();
        Http::assertSentCount(1);
        $this->assertSame(1, TtsCache::count());
        Http::assertSent(fn ($r) => $r['voice'] === 'coral' && $r['input'] === 'The shop closes on Sunday.');

        // Giọng nam / tiếng Trung là đoạn khác
        $s->get($this->url('The shop closes on Sunday.', 'en', 'male'))->assertOk();
        $s->get($this->url('商店星期天关门。', 'zh'))->assertOk();
        Http::assertSentCount(3);
        Http::assertSent(fn ($r) => $r['voice'] === 'onyx');
    }

    public function test_disabled_or_invalid_returns_error_so_browser_falls_back(): void
    {
        Http::fake();
        $student = Student::forceCreate(['last_name' => 'An', 'email' => 'an@x.vn', 'password' => bcrypt('x')]);
        $s = $this->actingAs($student, 'student');
        $s->get($this->url(str_repeat('a', 1300)))->assertStatus(422);
        $s->get('/tts?t=%%%')->assertStatus(422);
        config(['ai.tts_enabled' => false]);
        $s->get($this->url('Hello'))->assertStatus(503);
        Http::assertNothingSent();
    }
}
