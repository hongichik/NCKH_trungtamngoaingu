<?php

/** Video bài học (V2 bước 4). */
return [
    // Link phát ký số hết hạn sau bấy nhiêu giây (trình phát tự xin link mới khi sắp hết hạn).
    'link_ttl' => (int) env('VIDEO_LINK_TTL', 300),

    // Mỗi học viên chỉ xem trên 1 thiết bị / phiên đăng nhập tại một thời điểm.
    'single_session' => (bool) env('VIDEO_SINGLE_SESSION', true),

    // Tối đa dung lượng 1 video tải lên hosting (MB).
    'max_upload_mb' => (int) env('VIDEO_MAX_UPLOAD_MB', 1024),

    // Xem được bao nhiêu % thì tính là xem xong.
    'complete_percent' => 90,

    // Bunny Stream (quyết định 1a): bật khi có tài khoản. Token Authentication của thư viện video.
    'bunny' => [
        'library_id' => env('BUNNY_STREAM_LIBRARY_ID'),
        'token_key' => env('BUNNY_STREAM_TOKEN_KEY'),
    ],
];
