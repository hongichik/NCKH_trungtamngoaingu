<?php

use App\Support\Ai\Jobs\PingJob;
use App\Support\AiImport\Jobs\ImportBuildJob;
use App\Support\AiImport\Jobs\ImportOcrJob;
use App\Support\AiImport\Jobs\ImportPlanJob;
use App\Support\AiImport\Jobs\ImportTranscribeJob;
use App\Support\AiImport\Jobs\ImportTtsJob;
use App\Support\CustomExercise\Jobs\CustomGradeJob;
use App\Support\CustomExercise\Jobs\CustomItemAiJob;
use App\Support\Exercise\Jobs\GradeAttemptJob;
use App\Support\Progress\PracticeJob;
use App\Support\Vocab\Jobs\LessonVocabularyJob;

/*
| Cấu hình AI. Các giá trị AI_* / OPENAI_* sửa được trong .env hoặc ở
| Admin > Quản trị hệ thống > AI (chỉ chủ hệ thống).
| Không viết cứng tên model ở chỗ khác trong code — luôn lấy từ đây.
*/

return [

    'provider' => env('AI_PROVIDER', 'openai'),

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
    ],

    // Model cho từng chức năng (để tối ưu chi phí: việc nhẹ dùng model rẻ).
    'models' => [
        'analysis' => env('AI_MODEL_ANALYSIS', 'gpt-6-luna'),   // phân tích tài liệu / đoạn giáo trình
        'generate' => env('AI_MODEL_GENERATE', 'gpt-6-luna'),   // sinh bài tập
        'grading' => env('AI_MODEL_GRADING', 'gpt-6.1-terra'),  // chấm Nói / Viết
        'ocr' => env('AI_MODEL_OCR', 'gpt-6-luna'),             // đọc trang scan
        'transcribe' => env('AI_MODEL_TRANSCRIBE', 'gpt-transcribe'),
        'tts' => env('AI_MODEL_TTS', 'gpt-4o-mini-tts'),
    ],

    'tts_enabled' => (bool) env('AI_TTS_ENABLED', false),

    // Ghi âm bài Nói được giữ bao nhiêu ngày để giáo viên nghe lại (0 = giữ mãi). Tự xoá mỗi ngày qua ai:work.
    'recordings_keep_days' => (int) env('AI_RECORDINGS_KEEP_DAYS', 30),

    // Thời gian chờ mỗi lượt gọi (giây) và số lần thử lại khi lỗi tạm thời.
    'timeout' => (int) env('AI_TIMEOUT', 45),
    'retries' => (int) env('AI_RETRIES', 2),
    // Số lượt gọi AI gửi CÙNG LÚC khi một việc có nhiều phần độc lập (dựng nhiều phần bài tập, OCR nhiều trang).
    'concurrency' => (int) env('AI_CONCURRENCY', 8),

    // Hạn mức (0 = không giới hạn).
    'limits' => [
        'per_actor_daily' => (int) env('AI_LIMIT_ACTOR_DAILY', 100),   // lượt / người / ngày
        'monthly_calls' => (int) env('AI_LIMIT_MONTHLY_CALLS', 10000), // lượt / tháng toàn hệ thống
        'monthly_budget_usd' => (float) env('AI_BUDGET_MONTHLY_USD', 20), // USD / tháng toàn hệ thống
    ],

    // Đơn giá (USD) để ước tính chi phí — chỉnh theo bảng giá thực tế của model đang dùng.
    'pricing' => [
        'input_per_1m_tokens' => (float) env('AI_PRICE_INPUT_1M', 1.25),
        'output_per_1m_tokens' => (float) env('AI_PRICE_OUTPUT_1M', 10),
        'transcribe_per_minute' => (float) env('AI_PRICE_TRANSCRIBE_MIN', 0.006),
        'tts_per_1m_chars' => (float) env('AI_PRICE_TTS_1M_CHARS', 15),
    ],

    // Hàng đợi việc nền.
    'jobs' => [
        'max_attempts' => 3,
        'stale_minutes' => 10,     // việc "đang chạy" quá lâu (tiến trình bị ngắt) → trả về hàng chờ
        'job_budget_seconds' => 25, // không bắt đầu việc mới nếu còn ít hơn chừng này giây
        // Số tiến trình (= số dòng cron) = số giáo viên được xử lý CÙNG LÚC. Mỗi giáo viên một làn:
        // việc của cùng người chạy lần lượt; nhiều người hơn số tiến trình thì xoay vòng.
        // Mỗi tiến trình chạy liên tục tới khi hết việc (tối đa 50 phút/lượt), cron mỗi phút canh gác.
        'workers' => (int) env('AI_WORKERS', 3),
        // Hết việc thì tiến trình chờ thêm chừng này giây (kiểm tra hàng đợi mỗi 2 giây) rồi mới dừng
        // → việc mới được nhận gần như ngay, không phải đợi lượt cron kế tiếp.
        'idle_seconds' => (int) env('AI_WORKER_IDLE', 55),
        // Loại việc => lớp xử lý (implements App\Support\Ai\Jobs\AiJobHandler)
        'handlers' => [
            'ping' => PingJob::class,
            'exercise_grade' => GradeAttemptJob::class,
            'practice_generate' => PracticeJob::class,
            'custom_grade' => CustomGradeJob::class,
            'ai_import_ocr' => ImportOcrJob::class,
            'ai_import_plan' => ImportPlanJob::class,
            'ai_import_build' => ImportBuildJob::class,
            'ai_import_tts' => ImportTtsJob::class,
            'ai_import_transcribe' => ImportTranscribeJob::class,
            'custom_item_ai' => CustomItemAiJob::class,
            'lesson_vocabulary' => LessonVocabularyJob::class,
        ],
    ],

];
