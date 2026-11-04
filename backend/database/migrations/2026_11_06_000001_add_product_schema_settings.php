<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Values for the schema.org return policy and shipping details on product
 * pages, matching the shop's "Chính sách giao hàng, đổi trả và hoàn tiền"
 * (1 đổi 1 trong 07 ngày). Editable in Cài đặt → SEO.
 */
return new class extends Migration
{
    private const ROWS = [
        ['schema_return_days', '7', 'number', 'Schema: số ngày đổi trả (merchantReturnDays)'],
        ['schema_return_policy_path', '/chinh-sach-giao-hang-doi-tra-va-hoan-tien', 'text', 'Schema: đường dẫn trang chính sách đổi trả'],
        ['schema_shipping_handling_days_min', '0', 'number', 'Schema: thời gian chuẩn bị hàng tối thiểu (ngày)'],
        ['schema_shipping_handling_days_max', '1', 'number', 'Schema: thời gian chuẩn bị hàng tối đa (ngày)'],
        ['schema_shipping_transit_days_min', '1', 'number', 'Schema: thời gian vận chuyển tối thiểu (ngày)'],
        ['schema_shipping_transit_days_max', '4', 'number', 'Schema: thời gian vận chuyển tối đa (ngày)'],
    ];

    public function up(): void
    {
        foreach (self::ROWS as [$key, $value, $type, $label]) {
            if (DB::table('settings')->where('key', $key)->exists()) {
                continue;
            }
            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'group' => 'seo',
                'type' => $type,
                'options' => null,
                'label' => $label,
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Cache::forget('settings.public');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::ROWS, 0))->delete();
    }
};
