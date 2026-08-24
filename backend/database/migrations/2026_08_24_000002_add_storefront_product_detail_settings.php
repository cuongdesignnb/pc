<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SETTINGS = [
        [
            'key' => 'storefront_warehouse_addresses',
            'value' => "Cửa hàng 1\n📍 34 Hồ Tùng Mậu, Hà Nội\n☎ 0911 042 665\n\nCửa hàng 2\n📍 87 Nguyễn Trãi, Hà Nội\n☎ 0904 666 488",
            'group' => 'contact',
            'type' => 'textarea',
            'label' => 'Địa chỉ kho hàng trên trang sản phẩm',
            'is_public' => true,
        ],
        [
            'key' => 'storefront_warranty_information',
            'value' => "✅ Bảo hành chính hãng theo từng sản phẩm.\n\n✅ Hỗ trợ tư vấn và tiếp nhận bảo hành tại cửa hàng.\n\n✅ Miễn phí giao hàng theo chính sách vận chuyển hiện hành.",
            'group' => 'contact',
            'type' => 'textarea',
            'label' => 'Thông tin bảo hành trên trang sản phẩm',
            'is_public' => true,
        ],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['created_at' => now(), 'updated_at' => now()]),
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::SETTINGS, 'key'))->delete();
    }
};
