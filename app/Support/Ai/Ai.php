<?php

namespace App\Support\Ai;

use App\Models\AiUsage;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Điểm gọi AI duy nhất của hệ thống.
 *
 *   app(Ai::class)->for($user)->json('analysis', 'chunk_analysis', $vars, $schema, 'analysis');
 *
 * Mỗi lượt gọi: kiểm tra hạn mức → gọi nhà cung cấp (thử lại khi lỗi tạm thời /
 * JSON sai cấu trúc) → ghi vào bảng ai_usage kèm chi phí ước tính.
 */
class Ai
{
    private ?string $actorType = null;

    private ?int $actorId = null;

    public function __construct(private AiProvider $provider, private AiLimiter $limiter) {}

    /**
     * Gắn người dùng chịu lượt gọi (để tính hạn mức theo người).
     * $actor: App\Models\User, App\Models\Student, hoặc null (hệ thống).
     */
    public function for(?object $actor): static
    {
        $clone = clone $this;
        $clone->actorType = $actor ? strtolower(class_basename($actor)) : null;
        $clone->actorId = $actor?->getKey();

        return $clone;
    }

    /**
     * Sinh JSON từ một prompt trong resources/prompts.
     *
     * @param  string  $feature  analysis | generate | grading | ocr (quyết định model)
     * @param  array  $schema  JSON Schema của kết quả
     */
    public function json(string $feature, string $promptName, array $vars, array $schema, string $schemaName, array $extra = []): array
    {
        return $this->jsonRaw($feature, Prompts::render($promptName, $vars), $schema, $schemaName, $extra);
    }

    /** Như json() nhưng truyền thẳng nội dung prompt. */
    public function jsonRaw(string $feature, string $prompt, array $schema, string $schemaName, array $extra = []): array
    {
        $model = $this->model($feature);
        $opts = [
            'system' => $extra['system'] ?? Prompts::render('system'),
            'prompt' => $prompt,
            'schema' => $schema,
            'schema_name' => $schemaName,
            'model' => $model,
            'images' => $extra['images'] ?? [],
            'timeout' => $extra['timeout'] ?? (int) config('ai.timeout', 45),
        ];

        return $this->call($feature, $model, function () use ($opts, $schema) {
            $res = $this->provider->generateJSON($opts);
            if ($errors = JsonSchema::validate($res->data, $schema)) {
                throw new AiException('AI trả về dữ liệu sai cấu trúc: '.implode('; ', array_slice($errors, 0, 3)), true);
            }

            return $res;
        })->data;
    }

    /**
     * Gửi nhiều yêu cầu JSON SONG SONG (tối đa ai.concurrency yêu cầu một lúc) — dùng khi có nhiều phần độc lập
     * (dựng nhiều phần bài tập, OCR nhiều trang) để không phải chờ lần lượt từng lượt gọi.
     * Lỗi tạm thời / sai lược đồ được gửi lại (cũng song song). Hết hạn mức → ném AiLimitException như jsonRaw().
     *
     * @param  array<array-key, array{prompt: string, schema: array, name: string, extra?: array}>  $requests
     * @return array<array-key, array|AiException> dữ liệu JSON, hoặc lỗi của riêng yêu cầu đó
     */
    public function jsonRawMany(string $feature, array $requests): array
    {
        $model = $this->model($feature);
        $attempts = 1 + max(0, (int) config('ai.retries', 2));
        $results = [];

        foreach (array_chunk($requests, max(1, (int) config('ai.concurrency', 8)), true) as $group) {
            for ($i = 1; $group && $i <= $attempts; $i++) {
                $this->limiter->check($this->actorType, $this->actorId);
                $opts = array_map(fn ($r) => [
                    'system' => $r['extra']['system'] ?? Prompts::render('system'),
                    'prompt' => $r['prompt'], 'schema' => $r['schema'], 'schema_name' => $r['name'], 'model' => $model,
                    'images' => $r['extra']['images'] ?? [], 'timeout' => $r['extra']['timeout'] ?? (int) config('ai.timeout', 45),
                ], $group);
                try {
                    $batch = $this->provider->generateJSONMany($opts);
                } catch (AiException $e) {
                    $batch = array_fill_keys(array_keys($group), $e);
                }
                $retry = [];
                foreach ($group as $key => $r) {
                    $res = $batch[$key] ?? new AiException('Không có phản hồi từ AI.', true);
                    if ($res instanceof AiResponse && ($errors = JsonSchema::validate($res->data, $r['schema']))) {
                        $this->record($feature, $model, $res, false, 'Sai cấu trúc');
                        $res = new AiException('AI trả về dữ liệu sai cấu trúc: '.implode('; ', array_slice($errors, 0, 3)), true);
                    } elseif ($res instanceof AiResponse) {
                        $this->record($feature, $model, $res, true);
                        $results[$key] = $res->data;

                        continue;
                    }
                    if ($res->retryable && $i < $attempts) {
                        $retry[$key] = $r;

                        continue;
                    }
                    $this->record($feature, $model, null, false, $res->getMessage());
                    $results[$key] = $res;
                }
                $group = $retry;
                if ($group) {
                    Sleep::for(2 ** ($i - 1))->seconds();
                }
            }
        }

        return array_replace(array_intersect_key($requests, $results), $results);
    }

    /** Đọc chữ từ ảnh trang sách (OCR). */
    public function ocr(string $imagePath, string $language = 'en'): string
    {
        $schema = ['type' => 'object', 'properties' => ['text' => ['type' => 'string']], 'required' => ['text']];

        return $this->json('ocr', 'ocr_page', ['language' => $language], $schema, 'ocr_page', ['images' => [$imagePath]])['text'];
    }

    /**
     * OCR nhiều ảnh cùng lúc (song song).
     *
     * @param  array<array-key, string>  $imagePaths
     * @return array<array-key, string|AiException>
     */
    public function ocrMany(array $imagePaths, string $language = 'en'): array
    {
        $schema = ['type' => 'object', 'properties' => ['text' => ['type' => 'string']], 'required' => ['text']];
        $prompt = Prompts::render('ocr_page', ['language' => $language]);
        $res = $this->jsonRawMany('ocr', array_map(fn ($path) => ['prompt' => $prompt, 'schema' => $schema, 'name' => 'ocr_page', 'extra' => ['images' => [$path]]], $imagePaths));

        return array_map(fn ($r) => $r instanceof AiException ? $r : (string) $r['text'], $res);
    }

    /**
     * @return array{text: string, language: ?string}
     */
    public function transcribe(string $audioPath, array $opts = []): array
    {
        $model = $this->model('transcribe');

        return $this->call('transcribe', $model, fn () => $this->provider->transcribe($audioPath, $opts + ['model' => $model, 'timeout' => 90]))->data;
    }

    /** @return string nội dung mp3 */
    public function speak(string $text, string $voice, ?string $instructions = null): string
    {
        if (! config('ai.tts_enabled')) {
            throw new AiException('Giọng đọc AI đang tắt (bật trong Quản trị hệ thống > AI).');
        }
        $model = $this->model('tts');

        return $this->call('tts', $model, fn () => $this->provider->speak($text, ['model' => $model, 'voice' => $voice, 'instructions' => $instructions]))->data;
    }

    public function model(string $feature): string
    {
        return (string) (config("ai.models.{$feature}") ?: config('ai.models.generate'));
    }

    /**
     * Kiểm tra hạn mức, gọi với cơ chế thử lại, ghi nhận chi phí.
     */
    private function call(string $feature, string $model, callable $fn): AiResponse
    {
        $this->limiter->check($this->actorType, $this->actorId);

        $attempts = 1 + max(0, (int) config('ai.retries', 2));
        $last = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $res = $fn();
                $this->record($feature, $model, $res, true);

                return $res;
            } catch (AiException $e) {
                $last = $e;
                if (! $e->retryable || $i === $attempts) {
                    break;
                }
                Sleep::for(2 ** ($i - 1))->seconds(); // 1s, 2s, 4s...
            } catch (Throwable $e) {
                $last = new AiException('Lỗi không mong muốn khi gọi AI: '.$e->getMessage());
                break;
            }
        }

        $this->record($feature, $model, null, false, $last?->getMessage());

        throw $last;
    }

    private function record(string $feature, string $model, ?AiResponse $res, bool $success, ?string $error = null): void
    {
        $p = config('ai.pricing');
        $cost = $res ? (
            $res->inputTokens / 1e6 * $p['input_per_1m_tokens']
            + $res->outputTokens / 1e6 * $p['output_per_1m_tokens']
            + $res->seconds / 60 * $p['transcribe_per_minute']
            + $res->chars / 1e6 * $p['tts_per_1m_chars']
        ) : 0;

        AiUsage::create([
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'feature' => $feature,
            'model' => $model,
            'input_tokens' => $res?->inputTokens ?? 0,
            'output_tokens' => $res?->outputTokens ?? 0,
            'units' => $res ? ($res->seconds ?: $res->chars) : 0,
            'cost_usd' => round($cost, 6),
            'success' => $success,
            'error' => $error ? mb_substr($error, 0, 500) : null,
        ]);
    }
}
