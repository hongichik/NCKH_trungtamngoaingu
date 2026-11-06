<?php

namespace App\Support\Ai\Jobs;

use App\Models\AiJob;

/**
 * Lớp xử lý một loại việc nền. Mỗi lần handle() phải xong trong ~25 giây
 * (giới hạn của hosting); việc lớn hơn thì chia thành nhiều việc nhỏ.
 */
interface AiJobHandler
{
    /**
     * @return array|null kết quả lưu vào ai_jobs.result
     *
     * @throws \Throwable lỗi → hàng đợi tự thử lại (nếu là lỗi tạm thời) hoặc đánh dấu "Lỗi"
     */
    public function handle(AiJob $job): ?array;

    // Tuỳ chọn: public function failed(AiJob $job, \Throwable $e): void
    // — được gọi khi việc bị đánh dấu "Lỗi" hẳn (hết lượt thử hoặc lỗi không thử lại được).
}
