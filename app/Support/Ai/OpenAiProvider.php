<?php

namespace App\Support\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI qua REST API (Laravel HTTP client, không dùng SDK).
 *  - Sinh JSON: Responses API (/responses) + Structured Outputs (json_schema strict).
 *  - Chép lời: /audio/transcriptions.
 *  - Giọng đọc: /audio/speech.
 * Lớp này chỉ gọi 1 lần; việc thử lại, hạn mức, ghi chi phí do App\Support\Ai\Ai đảm nhận.
 */
class OpenAiProvider implements AiProvider
{
    public function __construct(private string $apiKey, private string $baseUrl) {}

    private function client(int $timeout): PendingRequest
    {
        if ($this->apiKey === '') {
            throw new AiException('Chưa cấu hình OPENAI_API_KEY (Admin > Quản trị hệ thống > AI).', false, 500);
        }

        return Http::withToken($this->apiKey)->timeout($timeout)->connectTimeout(10);
    }

    public function generateJSON(array $opts): AiResponse
    {
        $res = $this->send(fn () => $this->client($opts['timeout'] ?? 45)->post($this->baseUrl.'/responses', $this->jsonBody($opts)));

        return $this->parseJson($res);
    }

    /**
     * Gửi nhiều yêu cầu JSON CÙNG LÚC (song song, Http::pool) — thời gian chờ ≈ yêu cầu chậm nhất thay vì cộng dồn.
     * Mỗi phần tử kết quả là AiResponse hoặc AiException (lỗi của yêu cầu đó, không làm hỏng các yêu cầu khác).
     *
     * @param  array<array-key, array>  $list
     * @return array<array-key, AiResponse|AiException>
     */
    public function generateJSONMany(array $list): array
    {
        $this->client(1); // kiểm tra API key
        $responses = Http::pool(function (Pool $pool) use ($list) {
            foreach ($list as $key => $opts) {
                $pool->as('k'.$key)->withToken($this->apiKey)->timeout($opts['timeout'] ?? 45)->connectTimeout(10)
                    ->post($this->baseUrl.'/responses', $this->jsonBody($opts));
            }
        });

        $out = [];
        foreach (array_keys($list) as $key) {
            $r = $responses['k'.$key] ?? null;
            try {
                $out[$key] = $this->parseJson($this->send(function () use ($r) {
                    if ($r instanceof Response) {
                        return $r;
                    }
                    throw $r instanceof ConnectionException ? $r : new ConnectionException($r instanceof \Throwable ? $r->getMessage() : 'Không có phản hồi');
                }));
            } catch (AiException $e) {
                $out[$key] = $e;
            } catch (\Throwable $e) {
                $out[$key] = new AiException('Lỗi không mong muốn khi gọi AI: '.$e->getMessage());
            }
        }

        return $out;
    }

    private function jsonBody(array $opts): array
    {
        $content = [];
        foreach ($opts['images'] ?? [] as $path) {
            $mime = mime_content_type($path) ?: 'image/png';
            $content[] = ['type' => 'input_image', 'image_url' => "data:{$mime};base64,".base64_encode((string) file_get_contents($path))];
        }
        $content[] = ['type' => 'input_text', 'text' => $opts['prompt']];

        return [
            'model' => $opts['model'],
            'input' => [
                ['role' => 'system', 'content' => $opts['system']],
                ['role' => 'user', 'content' => $content],
            ],
            'text' => ['format' => [
                'type' => 'json_schema',
                'name' => $opts['schema_name'],
                'strict' => true,
                'schema' => JsonSchema::strict($opts['schema']),
            ]],
            'store' => false,
        ];

    }

    private function parseJson(Response $res): AiResponse
    {
        $j = $res->json();

        if (($j['status'] ?? null) === 'incomplete') {
            throw new AiException('AI trả lời bị cắt giữa chừng (quá dài).', true);
        }

        $text = '';
        foreach ($j['output'] ?? [] as $out) {
            foreach (($out['type'] ?? '') === 'message' ? ($out['content'] ?? []) : [] as $c) {
                if (($c['type'] ?? '') === 'refusal') {
                    throw new AiException('AI từ chối xử lý nội dung này.', false, 422);
                }
                if (($c['type'] ?? '') === 'output_text') {
                    $text .= $c['text'];
                }
            }
        }

        $data = json_decode($text, true);
        if (! is_array($data)) {
            throw new AiException('AI trả về JSON không hợp lệ.', true);
        }

        return new AiResponse($data, (int) ($j['usage']['input_tokens'] ?? 0), (int) ($j['usage']['output_tokens'] ?? 0));
    }

    public function transcribe(string $audioPath, array $opts): AiResponse
    {
        $prompt = trim(($opts['prompt'] ?? '').(! empty($opts['keywords']) ? "\nVocabulary that may appear: ".implode(', ', $opts['keywords']).'.' : ''));
        $model = $opts['model'];
        $form = array_filter([
            'model' => $model,
            // whisper-1 trả về ngôn ngữ nhận diện được; các model mới chỉ trả text
            'response_format' => str_starts_with($model, 'whisper') ? 'verbose_json' : 'json',
            'prompt' => $prompt ?: null,
            // Chỉ ép ngôn ngữ khi có đúng 1 ngôn ngữ; nhiều ngôn ngữ thì để model tự nhận diện
            'language' => count($opts['languages'] ?? []) === 1 ? $opts['languages'][0] : null,
        ]);

        $res = $this->send(fn () => $this->client($opts['timeout'] ?? 90)
            ->attach('file', (string) file_get_contents($audioPath), basename($audioPath))
            ->post($this->baseUrl.'/audio/transcriptions', $form));
        $j = $res->json();

        return new AiResponse(
            ['text' => (string) ($j['text'] ?? ''), 'language' => $j['language'] ?? null],
            seconds: (float) ($j['duration'] ?? $opts['duration'] ?? 0),
        );
    }

    public function speak(string $text, array $opts): AiResponse
    {
        $res = $this->send(fn () => $this->client($opts['timeout'] ?? 60)->post($this->baseUrl.'/audio/speech', array_filter([
            'model' => $opts['model'],
            'input' => $text,
            'voice' => $opts['voice'],
            'instructions' => $opts['instructions'] ?? null,
            'response_format' => 'mp3',
        ])));

        return new AiResponse($res->body(), chars: mb_strlen($text));
    }

    /**
     * Gửi request và đổi lỗi HTTP thành AiException (có đánh dấu lỗi tạm thời hay không).
     */
    private function send(callable $request): Response
    {
        try {
            $res = $request();
        } catch (ConnectionException $e) {
            throw new AiException('Không kết nối được tới máy chủ AI (mạng chậm hoặc hết thời gian chờ).', true);
        }

        if ($res->successful()) {
            return $res;
        }

        $status = $res->status();
        $detail = (string) ($res->json('error.message') ?? '');
        $msg = match (true) {
            $status === 401 => 'API key OpenAI không hợp lệ hoặc đã bị thu hồi.',
            $status === 429 && str_contains($detail, 'quota') => 'Tài khoản OpenAI đã hết hạn mức / hết tiền.',
            $status === 429 => 'AI đang quá tải (429), sẽ thử lại.',
            $status >= 500 => "Máy chủ AI lỗi tạm thời ({$status}).",
            $status === 404 => 'Không tìm thấy model — kiểm tra lại tên model trong cấu hình AI.',
            default => "AI từ chối yêu cầu ({$status}).",
        };

        $retryable = ($status === 429 && ! str_contains($detail, 'quota')) || $status >= 500;

        throw new AiException($msg.($detail && ! $retryable ? ' Chi tiết: '.mb_substr($detail, 0, 300) : ''), $retryable, $status);
    }
}
