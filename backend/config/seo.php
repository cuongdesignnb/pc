<?php

return [
    // Production must provide this explicitly. It is never derived from the
    // incoming Host header or from the admin/API URL.
    'site_origin' => env('SEO_SITE_ORIGIN', env('CATALOG_STOREFRONT_URL', env('FRONTEND_URL'))),
    'slug_policy_version' => 'vn-v1',
    'sitemap_page_size' => 50000,
    'static_paths' => [
        '/', '/san-pham', '/danh-muc', '/tin-tuc', '/gioi-thieu', '/lien-he',
        '/bao-hanh', '/van-chuyen',
    ],
    'private_paths' => [
        '/api', '/admin', '/payment', '/payments', '/auth', '/dang-nhap', '/dang-ky', '/quen-mat-khau', '/tai-khoan',
        '/gio-hang', '/thanh-toan', '/don-hang', '/yeu-thich', '/cau-hinh',
    ],
];
