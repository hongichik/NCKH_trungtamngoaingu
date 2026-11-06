<?php

namespace App\Support\QuestionTypes\Catalog;

/**
 * Các loại câu hỏi đã có ở V1 (bảng questions.type), nay nằm trong thư viện.
 * Dữ liệu V1 không đổi; LegacyAdapter chuyển câu V1 sang nội dung của dạng tương ứng khi cần.
 */
class Legacy
{
    public static function types(): array
    {
        $v = fn (array $d) => $d + ['standard' => 'v1', 'lang' => 'any', 'description' => 'Dạng bài sinh tự động từ giáo trình ở V1 (loại "'.$d['legacy'].'").'];

        return [
            $v(['code' => 'v1.mcq', 'legacy' => 'mcq', 'name' => 'V1 — Trắc nghiệm', 'skill' => 'reading', 'engine' => 'choice',
                'sample' => ['questions' => [['id' => 'R1', 'prompt' => 'What is the main idea of the text?', 'options' => ['Travel tips', 'A school trip', 'A new teacher'], 'answer' => 'B']]]]),
            $v(['code' => 'v1.true_false', 'legacy' => 'true_false', 'name' => 'V1 — Đúng / Sai', 'skill' => 'reading', 'engine' => 'choice',
                'options' => ['choices' => ['True' => 'Đúng (True)', 'False' => 'Sai (False)']],
                'sample' => ['questions' => [['id' => 'R2', 'prompt' => 'The trip was on Monday.', 'answer' => 'False']]]]),
            $v(['code' => 'v1.cloze', 'legacy' => 'cloze', 'name' => 'V1 — Điền vào đoạn văn (chọn đáp án)', 'skill' => 'reading', 'engine' => 'choice',
                'sample' => ['stimulus' => ['passage' => 'We (1) ___ to the zoo last weekend.'], 'questions' => [['id' => 'R3', 'prompt' => '(1)', 'options' => ['go', 'went', 'gone'], 'answer' => 'B']]]]),
            $v(['code' => 'v1.ordering', 'legacy' => 'ordering', 'name' => 'V1 — Sắp xếp', 'skill' => 'reading', 'engine' => 'ordering', 'scoring' => ['partial' => false],
                'sample' => ['prompt' => 'Put the events in order.', 'items' => ['They bought tickets.', 'They got on the train.', 'They arrived in Hue.']]]),
            $v(['code' => 'v1.gap_fill', 'legacy' => 'gap_fill', 'name' => 'V1 — Điền từ nghe được', 'skill' => 'listening', 'engine' => 'gap_text',
                'sample' => ['stimulus' => ['transcript' => 'The concert starts at seven.'], 'text' => "The concert starts at ___.\n→ [[L1]]", 'answers' => ['L1' => ['seven', '7']]]]),
            $v(['code' => 'v1.transformation', 'legacy' => 'transformation', 'name' => 'V1 — Viết lại câu', 'skill' => 'writing', 'engine' => 'short_text',
                'sample' => ['questions' => [['id' => 'W1', 'prompt' => 'He started learning French two years ago.', 'start' => 'He has', 'answers' => ['He has learned French for two years.', 'He has been learning French for two years.']]]]]),
            $v(['code' => 'v1.paragraph', 'legacy' => 'paragraph', 'name' => 'V1 — Viết đoạn văn', 'skill' => 'writing', 'engine' => 'writing', 'grading' => 'ai',
                'sample' => ['prompt' => 'Write about your favourite holiday.', 'min_words' => 60, 'max_words' => 100]]),
            $v(['code' => 'v1.speaking', 'legacy' => 'speaking', 'name' => 'V1 — Nhiệm vụ nói', 'skill' => 'speaking', 'engine' => 'speaking', 'grading' => 'ai',
                'sample' => ['prompt' => 'Talk about your family for about 45 seconds.', 'speak_seconds' => 45]]),
            $v(['code' => 'v1.read_aloud', 'legacy' => 'read_aloud', 'name' => 'V1 — Đọc to', 'skill' => 'speaking', 'engine' => 'speaking', 'grading' => 'ai',
                'sample' => ['read_text' => 'I like reading books in the evening.', 'speak_seconds' => 20]]),
        ];
    }
}
