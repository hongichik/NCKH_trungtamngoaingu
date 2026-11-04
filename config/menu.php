<?php

/*
 * Menu locations on the public site and the fixed system pages an admin can
 * link to without typing a URL. "active" lists route-name patterns that should
 * highlight the item; "query" (optional) must also match the current request.
 */
return [
    'locations' => [
        'header' => ['label' => 'Menu chính (thanh điều hướng)', 'nested' => true],
        'footer_explore' => ['label' => 'Chân trang - cột "Khám phá"', 'nested' => false],
        'footer_support' => ['label' => 'Chân trang - cột "Hỗ trợ"', 'nested' => false],
    ],

    'routes' => [
        'home' => ['label' => 'Trang chủ', 'route' => 'home', 'active' => ['home']],
        'blog.index' => ['label' => 'Tin tức', 'route' => 'blog.index', 'active' => ['blog.index', 'category.show']],
        'galleries.index' => ['label' => 'Thư viện ảnh', 'route' => 'galleries.index', 'active' => ['galleries.*']],
        'portal.auth.hsk' => ['label' => 'Đăng ký thi HSK', 'route' => 'portal.auth', 'params' => ['type' => 'hsk'], 'active' => ['portal.auth'], 'query' => ['type' => 'hsk']],
        'portal.auth.cntt' => ['label' => 'Đăng ký thi Chứng chỉ Tin học', 'route' => 'portal.auth', 'params' => ['type' => 'cntt'], 'active' => ['portal.auth'], 'query' => ['type' => 'cntt']],
        'certificates.lookup' => ['label' => 'Tra cứu chứng chỉ', 'route' => 'certificates.lookup', 'active' => ['certificates.lookup']],
        'contact.create' => ['label' => 'Liên hệ', 'route' => 'contact.create', 'active' => ['contact.create']],
    ],
];
