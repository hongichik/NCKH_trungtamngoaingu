<?php

/*
| Các giá trị DB_SYNC_* có thể sửa trực tiếp trong .env hoặc từ
| Admin > Quản trị hệ thống > Đồng bộ (chỉ chủ hệ thống).
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Token bảo mật
    |--------------------------------------------------------------------------
    | Chuỗi bí mật dùng chung giữa máy local và máy chủ. Phải giống nhau ở cả
    | hai nơi. Nếu để trống, API đồng bộ sẽ bị tắt hoàn toàn (trả về 404).
    */
    'token' => env('DB_SYNC_TOKEN'),

    // Cho phép máy khác đẩy / kéo CSDL qua API.
    'enabled' => (bool) env('DB_SYNC_ENABLED', true),

    // Cho phép máy khác đẩy / kéo file trong các thư mục bên dưới qua API.
    'files_enabled' => (bool) env('DB_SYNC_FILES_ENABLED', false),

    // Thư mục được đồng bộ file (tính từ thư mục gốc dự án, cách nhau bởi dấu phẩy).
    // Chỉ chấp nhận thư mục nằm trong storage/app/ hoặc public/.
    'file_dirs' => array_values(array_filter(array_map('trim', explode(',', (string) env('DB_SYNC_FILE_DIRS', 'storage/app/public'))))),

    /*
    |--------------------------------------------------------------------------
    | Địa chỉ máy chủ đích
    |--------------------------------------------------------------------------
    | Dùng ở máy local khi chạy các lệnh db:sync-* / files:sync-*.
    */
    'remote_url' => env('DB_SYNC_REMOTE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Các bảng không bao giờ đồng bộ
    |--------------------------------------------------------------------------
    | Bảng hệ thống / dữ liệu tạm thời, mang tính riêng của từng máy.
    | Có thể thêm bảng qua DB_SYNC_EXCLUDE (cách nhau bởi dấu phẩy).
    */
    'excluded_tables' => array_values(array_unique(array_merge([
        // Bảng hệ thống, riêng của từng máy
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
        // Hàng đợi & lượt dùng AI: riêng của từng máy
        'ai_jobs',
        'ai_usage',
    ], array_filter(array_map('trim', explode(',', (string) env('DB_SYNC_EXCLUDE', ''))))))),

    /*
    |--------------------------------------------------------------------------
    | Bảng được bảo vệ (chỉ KÉO về, không bao giờ bị ĐẨY lên ghi đè)
    |--------------------------------------------------------------------------
    | Dữ liệu giáo viên tạo trên máy chủ (file nằm ở storage/app/private của máy chủ).
    | db:sync-pull kéo được về máy local để kiểm tra; db:sync-push / API nhập dữ liệu
    | trên máy chủ mặc định bỏ qua các bảng này — chỉ ghi đè khi chạy
    | db:sync-push --include-protected (đồng bộ trọn vẹn CSDL).
    */
    'protected_tables' => [
        'teachers',
        'textbooks',
        'textbook_pages',
        'textbook_units',
        'textbook_chunks',
        'unit_profiles',
        'audio_tracks',
        'courses',
        'exercise_sets',
        'questions',
        'exercise_attempts',
        'unit_tests',
        'classrooms',
        'classroom_student',
        'assignments',
        'tts_cache',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sao lưu trước khi ghi đè
    |--------------------------------------------------------------------------
    | Trước mỗi lần nhập dữ liệu, toàn bộ dữ liệu hiện tại được lưu vào
    | storage/app/db-sync-backups. Giữ lại N bản gần nhất.
    */
    'backup_keep' => (int) env('DB_SYNC_BACKUP_KEEP', 10),

    // Số dòng mỗi lần insert.
    'chunk_size' => 500,

    // Khi đẩy lên máy chủ, dữ liệu được chia thành nhiều request, mỗi phần
    // khoảng chừng này byte trước khi nén (sau gzip thường nhỏ hơn 5–10 lần).
    'part_bytes' => (int) env('DB_SYNC_PART_BYTES', 4 * 1024 * 1024),

    // Thời gian chờ tối đa (giây) cho request HTTP từ lệnh artisan.
    'http_timeout' => (int) env('DB_SYNC_HTTP_TIMEOUT', 300),

];
