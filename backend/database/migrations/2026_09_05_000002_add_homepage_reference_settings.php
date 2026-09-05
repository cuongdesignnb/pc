<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Register editable homepage settings without inventing a campaign or
     * service commitments. Existing admin values are preserved.
     *
     * @var array<int, array{key: string, value: string, group: string, type: string, label: string, is_public: bool}>
     */
    private const SETTINGS = [
        [
            'key' => 'homepage_featured_category_slugs',
            'value' => '["pc-gaming","laptop-gaming","vga","cpu","mainboard","ram","ssd","man-hinh","ghe-gaming"]',
            'group' => 'homepage',
            'type' => 'json',
            'label' => 'Danh mục nổi bật homepage',
            'is_public' => true,
        ],
        [
            'key' => 'homepage_flash_sale_enabled',
            'value' => '1',
            'group' => 'homepage',
            'type' => 'boolean',
            'label' => 'Bật Flash Sale homepage',
            'is_public' => true,
        ],
        [
            'key' => 'homepage_flash_sale_ends_at',
            'value' => '',
            'group' => 'homepage',
            'type' => 'text',
            'label' => 'Thời điểm kết thúc Flash Sale homepage',
            'is_public' => true,
        ],
        [
            'key' => 'homepage_service_shipping_title',
            'value' => 'Giao hàng',
            'group' => 'homepage',
            'type' => 'text',
            'label' => 'Dịch vụ homepage: giao hàng',
            'is_public' => true,
        ],
        [
            'key' => 'homepage_service_shipping_text',
            'value' => 'Theo chính sách vận chuyển',
            'group' => 'homepage',
            'type' => 'text',
            'label' => 'Mô tả dịch vụ homepage: giao hàng',
            'is_public' => true,
        ],
        [
            'key' => 'homepage_service_authenticity_title',
            'value' => 'Thông tin sản phẩm',
            'group' => 'homepage',
            'type' => 'text',
            'label' => 'Dịch vụ homepage: chính hãng',
            'is_public' => true,
        ],
        [
            'key' => 'homepage_service_authenticity_text',
            'value' => 'Xem xuất xứ và bảo hành',
            'group' => 'homepage',
            'type' => 'text',
            'label' => 'Mô tả dịch vụ homepage: chính hãng',
            'is_public' => true,
        ],
        [
            'key' => 'homepage_service_installment_title',
            'value' => 'Thanh toán',
            'group' => 'homepage',
            'type' => 'text',
            'label' => 'Dịch vụ homepage: trả góp',
            'is_public' => true,
        ],
        [
            'key' => 'homepage_service_installment_text',
            'value' => 'Theo phương thức hỗ trợ',
            'group' => 'homepage',
            'type' => 'text',
            'label' => 'Mô tả dịch vụ homepage: trả góp',
            'is_public' => true,
        ],
        [
            'key' => 'homepage_service_support_title',
            'value' => 'Hỗ trợ kỹ thuật',
            'group' => 'homepage',
            'type' => 'text',
            'label' => 'Dịch vụ homepage: hỗ trợ',
            'is_public' => true,
        ],
        [
            'key' => 'homepage_service_support_text',
            'value' => 'Tư vấn tận tâm',
            'group' => 'homepage',
            'type' => 'text',
            'label' => 'Mô tả dịch vụ homepage: hỗ trợ',
            'is_public' => true,
        ],
        [
            'key' => 'homepage_service_returns_title',
            'value' => 'Đổi trả dễ dàng',
            'group' => 'homepage',
            'type' => 'text',
            'label' => 'Dịch vụ homepage: đổi trả',
            'is_public' => true,
        ],
        [
            'key' => 'homepage_service_returns_text',
            'value' => 'Theo chính sách đổi trả',
            'group' => 'homepage',
            'type' => 'text',
            'label' => 'Mô tả dịch vụ homepage: đổi trả',
            'is_public' => true,
        ],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as $setting) {
            if (DB::table('settings')->where('key', $setting['key'])->exists()) {
                continue;
            }

            DB::table('settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        // Settings are user-editable data and may predate this migration.
        // Retain them on rollback instead of deleting an admin's configuration.
    }
};
