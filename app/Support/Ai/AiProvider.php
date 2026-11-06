<?php

namespace App\Support\Ai;

/**
 * Giao diện chung cho mọi nhà cung cấp AI. Đổi nhà cung cấp bằng AI_PROVIDER
 * mà không phải sửa phần còn lại của hệ thống.
 */
interface AiProvider
{
    /**
     * Sinh JSON theo schema.
     *
     * @param  array{system: string, prompt: string, schema: array, schema_name: string, model: string, images?: list<string>, timeout?: int}  $opts
     *                                                                                                                                                images: đường dẫn file ảnh trên máy chủ
     */
    public function generateJSON(array $opts): AiResponse;

    /**
     * Gửi nhiều yêu cầu generateJSON cùng lúc. Kết quả giữ nguyên khoá: AiResponse hoặc AiException cho từng yêu cầu.
     *
     * @param  array<array-key, array>  $list
     * @return array<array-key, AiResponse|AiException>
     */
    public function generateJSONMany(array $list): array;

    /**
     * Chép lời file âm thanh. data = ['text' => string, 'language' => ?string]
     *
     * @param  array{model: string, languages?: list<string>, prompt?: string, keywords?: list<string>, duration?: float, timeout?: int}  $opts
     */
    public function transcribe(string $audioPath, array $opts): AiResponse;

    /**
     * Tạo giọng đọc. data = nội dung file mp3 (chuỗi nhị phân).
     *
     * @param  array{model: string, voice: string, instructions?: string, timeout?: int}  $opts
     */
    public function speak(string $text, array $opts): AiResponse;
}
