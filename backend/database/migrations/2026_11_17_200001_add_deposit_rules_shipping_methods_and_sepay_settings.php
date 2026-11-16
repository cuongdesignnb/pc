<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 10 đợt 4:
 *  - deposit rules by province, category and product, and a minimum order total;
 *  - ways to receive the order (Giao Hàng Tiết Kiệm, Ahamove, nhận tại cửa hàng)
 *    and free shipping for laptops from a set amount;
 *  - SePay keys editable in Cài đặt → Thanh toán.
 *
 * Every new switch is off and every new rule is empty, so checkout behaves as
 * before until the shop configures it.
 */
return new class extends Migration
{
    private const SETTINGS = [
        // ─── Thanh toán ─────────────────────────────────────────────
        ['key' => 'payment_deposit_min_total', 'value' => '0', 'group' => 'payment', 'type' => 'number',
            'label' => 'Không cho đặt cọc với đơn dưới (VNĐ, 0 = không giới hạn)'],
        ['key' => 'payment_deposit_province_rules', 'value' => '{}', 'group' => 'payment', 'type' => 'json',
            'label' => 'Đặt cọc theo tỉnh/thành'],
        ['key' => 'sepay_webhook_api_key', 'value' => '', 'group' => 'payment', 'type' => 'password',
            'label' => 'API Key webhook SePay (tự xác nhận chuyển khoản)'],
        ['key' => 'sepay_gateway_secret_key', 'value' => '', 'group' => 'payment', 'type' => 'password',
            'label' => 'Secret key cổng thanh toán SePay (IPN /payment/ipn, không bắt buộc)'],

        // ─── Vận chuyển ─────────────────────────────────────────────
        ['key' => 'shipping_standard_enabled', 'value' => '1', 'group' => 'shipping', 'type' => 'boolean',
            'label' => 'Giao hàng tiêu chuẩn: bật (dùng phí ship mặc định và mức miễn phí ship ở trên)'],
        ['key' => 'shipping_ghtk_enabled', 'value' => '0', 'group' => 'shipping', 'type' => 'boolean',
            'label' => 'Giao Hàng Tiết Kiệm: bật'],
        ['key' => 'shipping_ghtk_label', 'value' => 'Giao Hàng Tiết Kiệm', 'group' => 'shipping', 'type' => 'text',
            'label' => 'Giao Hàng Tiết Kiệm: tên hiển thị'],
        ['key' => 'shipping_ghtk_use_standard_fee', 'value' => '1', 'group' => 'shipping', 'type' => 'boolean',
            'label' => 'Giao Hàng Tiết Kiệm: tính phí như giao hàng tiêu chuẩn (tắt để dùng phí cố định bên dưới)'],
        ['key' => 'shipping_ghtk_fee', 'value' => '30000', 'group' => 'shipping', 'type' => 'number',
            'label' => 'Giao Hàng Tiết Kiệm: phí cố định (VNĐ)'],
        ['key' => 'shipping_ghtk_eta', 'value' => '2-4 ngày', 'group' => 'shipping', 'type' => 'text',
            'label' => 'Giao Hàng Tiết Kiệm: thời gian dự kiến'],
        ['key' => 'shipping_ahamove_enabled', 'value' => '0', 'group' => 'shipping', 'type' => 'boolean',
            'label' => 'Ahamove: bật'],
        ['key' => 'shipping_ahamove_label', 'value' => 'Ahamove (giao nhanh trong ngày)', 'group' => 'shipping', 'type' => 'text',
            'label' => 'Ahamove: tên hiển thị'],
        ['key' => 'shipping_ahamove_fee', 'value' => '50000', 'group' => 'shipping', 'type' => 'number',
            'label' => 'Ahamove: phí cố định (VNĐ)'],
        ['key' => 'shipping_ahamove_eta', 'value' => 'Trong ngày', 'group' => 'shipping', 'type' => 'text',
            'label' => 'Ahamove: thời gian dự kiến'],
        ['key' => 'shipping_ahamove_province_codes', 'value' => '["01"]', 'group' => 'shipping', 'type' => 'json',
            'label' => 'Ahamove: chỉ giao tại các tỉnh/thành'],
        ['key' => 'shipping_pickup_enabled', 'value' => '0', 'group' => 'shipping', 'type' => 'boolean',
            'label' => 'Nhận tại cửa hàng: bật'],
        ['key' => 'shipping_pickup_label', 'value' => 'Nhận tại cửa hàng', 'group' => 'shipping', 'type' => 'text',
            'label' => 'Nhận tại cửa hàng: tên hiển thị'],
        ['key' => 'shipping_pickup_address', 'value' => '', 'group' => 'shipping', 'type' => 'textarea',
            'label' => 'Nhận tại cửa hàng: địa chỉ cửa hàng (để trống = Địa chỉ cửa hàng ở mục Liên hệ)'],
        ['key' => 'shipping_pickup_eta', 'value' => '', 'group' => 'shipping', 'type' => 'text',
            'label' => 'Nhận tại cửa hàng: thời gian (ví dụ: Nhận trong giờ mở cửa 8h-21h)'],
        ['key' => 'shipping_free_laptop_min_subtotal', 'value' => '0', 'group' => 'shipping', 'type' => 'number',
            'label' => 'Miễn phí ship khi tiền laptop trong đơn từ (VNĐ, 0 = tắt)'],
    ];

    public function up(): void
    {
        foreach (['categories', 'products'] as $table) {
            if (! Schema::hasColumn($table, 'deposit_rule')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->json('deposit_rule')->nullable();
                });
            }
        }
        if (! Schema::hasColumn('orders', 'shipping_method')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('shipping_method', 30)->nullable()->after('shipping_fee');
            });
        }

        foreach (self::SETTINGS as $setting) {
            if (DB::table('settings')->where('key', $setting['key'])->exists()) {
                continue;
            }
            DB::table('settings')->insert($setting + [
                'options' => null,
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Cache::forget('settings.public');
        Cache::forget('settings.all');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::SETTINGS, 'key'))->delete();
        Cache::forget('settings.public');
        Cache::forget('settings.all');

        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('shipping_method'));
        foreach (['categories', 'products'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('deposit_rule'));
        }
    }
};
