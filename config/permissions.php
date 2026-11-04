<?php

return [
    'actions' => [
        'view' => 'Xem',
        'create' => 'Thêm',
        'edit' => 'Sửa',
        'delete' => 'Xoá',
        'reply' => 'Phản hồi',
    ],

    'modules' => [
        'posts' => ['label' => 'Bài viết', 'actions' => ['view', 'create', 'edit', 'delete']],
        'categories' => ['label' => 'Danh mục', 'actions' => ['view', 'create', 'edit', 'delete']],
        'students' => ['label' => 'Học viên', 'actions' => ['view', 'create', 'edit', 'delete']],
        'certificates' => ['label' => 'Chứng chỉ', 'actions' => ['view', 'create', 'edit', 'delete']],
        'galleries' => ['label' => 'Thư viện ảnh', 'actions' => ['view', 'create', 'edit', 'delete']],
        'pages' => ['label' => 'Trang tĩnh', 'actions' => ['view', 'create', 'edit', 'delete']],
        'contacts' => ['label' => 'Liên hệ', 'actions' => ['view', 'reply', 'delete']],
        'exams' => ['label' => 'Đợt thi & Lệ phí', 'actions' => ['view', 'create', 'edit', 'delete']],
        'exam_registrations' => ['label' => 'Đăng ký dự thi', 'actions' => ['view', 'edit', 'delete']],
        'menus' => ['label' => 'Menu website', 'actions' => ['view', 'create', 'edit', 'delete']],
        'banners' => ['label' => 'Banner trang chủ', 'actions' => ['view', 'create', 'edit', 'delete']],
        'settings' => ['label' => 'Cài đặt website', 'actions' => ['view', 'edit']],
    ],
];
