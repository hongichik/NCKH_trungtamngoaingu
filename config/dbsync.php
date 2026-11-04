<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token bảo mật
    |--------------------------------------------------------------------------
    | Chuỗi bí mật dùng chung giữa máy local và máy chủ. Phải giống nhau ở cả
    | hai nơi. Nếu để trống, API đồng bộ sẽ bị tắt hoàn toàn (trả về 404).
    | Tạo token: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    */
    'token' => env('DB_SYNC_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Địa chỉ máy chủ đích
    |--------------------------------------------------------------------------
    | Dùng ở máy local khi chạy `php artisan db:sync-push` / `db:sync-pull`.
    | Ví dụ: https://ngoaingu.example.com
    */
    'remote_url' => env('DB_SYNC_REMOTE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Các bảng không bao giờ đồng bộ
    |--------------------------------------------------------------------------
    | Bảng hệ thống / dữ liệu tạm thời, mang tính riêng của từng máy.
    */
    'excluded_tables' => [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
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

    // Thời gian chờ tối đa (giây) cho request HTTP từ lệnh artisan.
    'http_timeout' => (int) env('DB_SYNC_HTTP_TIMEOUT', 300),

];
