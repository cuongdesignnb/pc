<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Laptopplus batch 7:
 * - free text for the storefront top bar (empty keeps address + hours),
 * - ward and city of the shop for the LocalBusiness schema,
 * - an extra line in the footer "Thanh toán" box (e.g. "Hỗ trợ trả góp").
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'storefront_topbar_text', 'value' => '', 'group' => 'homepage', 'type' => 'text',
            'label' => 'Dòng chữ thanh trên cùng (để trống: hiện địa chỉ và giờ làm việc)'],
        ['key' => 'contact_address_locality', 'value' => 'Phường Xuân Phương', 'group' => 'contact', 'type' => 'text',
            'label' => 'Phường/xã của cửa hàng (dùng cho schema Google)'],
        ['key' => 'contact_address_region', 'value' => 'Hà Nội', 'group' => 'contact', 'type' => 'text',
            'label' => 'Tỉnh/thành phố của cửa hàng (dùng cho schema Google)'],
        ['key' => 'payment_footer_extra', 'value' => '', 'group' => 'payment', 'type' => 'text',
            'label' => 'Thêm dòng ở ô "Thanh toán" chân trang (ví dụ: Hỗ trợ trả góp)'],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as $setting) {
            if (! DB::table('settings')->where('key', $setting['key'])->exists()) {
                DB::table('settings')->insert($setting + [
                    'options' => null,
                    'is_public' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        Cache::forget('settings.public');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::SETTINGS, 'key'))->delete();
        Cache::forget('settings.public');
    }
};
