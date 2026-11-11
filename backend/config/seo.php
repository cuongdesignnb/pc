<?php

return [
    // Production must provide this explicitly. It is never derived from the
    // incoming Host header or from the admin/API URL.
    'site_origin' => env('SEO_SITE_ORIGIN', env('CATALOG_STOREFRONT_URL', env('FRONTEND_URL'))),
    'slug_policy_version' => 'vn-v1',
    'sitemap_page_size' => 50000,
    // "Giới thiệu" is a CMS page; it is listed from the pages table when active.
    'static_paths' => [
        '/', '/san-pham', '/danh-muc', '/tin-tuc', '/lien-he', '/tra-cuu-bao-hanh',
        '/bao-hanh', '/van-chuyen',
    ],
    // Price-range landing pages (D3): a page of their own on the storefront
    // with its own title, H1 and canonical, listed in the sitemap and in the
    // filter block of their category.
    'price_landings' => [
        'laptop-duoi-5-trieu' => [
            'category' => 'laptop-cu',
            'max_price' => 4999999,
            'label' => 'Dưới 5 triệu',
            'title' => 'Laptop cũ dưới 5 triệu',
            'meta_title' => 'Laptop cũ dưới 5 triệu giá rẻ, còn hàng',
            'meta_description' => 'Laptop cũ dưới 5 triệu còn hàng tại Laptop Plus: máy văn phòng, học tập đã kiểm tra kỹ, bảo hành rõ ràng, giao hàng toàn quốc.',
        ],
        'laptop-tu-5-den-8-trieu' => [
            'category' => 'laptop-cu',
            'min_price' => 5000000,
            'max_price' => 8000000,
            'label' => 'Từ 5 đến 8 triệu',
            'title' => 'Laptop cũ từ 5 đến 8 triệu',
            'meta_title' => 'Laptop cũ từ 5 đến 8 triệu, còn hàng',
            'meta_description' => 'Laptop cũ từ 5 đến 8 triệu còn hàng tại Laptop Plus: cấu hình tốt cho văn phòng, học tập, đã kiểm tra kỹ, bảo hành rõ ràng.',
        ],
        'laptop-tren-8-trieu' => [
            'category' => 'laptop-cu',
            'min_price' => 8000001,
            'label' => 'Trên 8 triệu',
            'title' => 'Laptop cũ trên 8 triệu',
            'meta_title' => 'Laptop cũ trên 8 triệu cấu hình cao, còn hàng',
            'meta_description' => 'Laptop cũ trên 8 triệu còn hàng tại Laptop Plus: máy doanh nhân, đồ hoạ, cấu hình cao, đã kiểm tra kỹ, bảo hành rõ ràng.',
        ],
    ],
    'private_paths' => [
        '/api', '/admin', '/payment', '/payments', '/auth', '/dang-nhap', '/dang-ky', '/quen-mat-khau', '/tai-khoan',
        '/gio-hang', '/thanh-toan', '/don-hang', '/yeu-thich', '/cau-hinh',
    ],
];
