<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Nút thanh toán / trả góp Bảo Kim" on product and cart pages. The storefront
 * reads it from the public settings; it stays off until the shop turns it on.
 */
return new class extends Migration
{
    private const KEY = 'baokim_button_enabled';

    public function up(): void
    {
        if (! DB::table('settings')->where('key', self::KEY)->exists()) {
            DB::table('settings')->insert([
                'key' => self::KEY,
                'value' => '0',
                'type' => 'boolean',
                'group' => 'payment',
                'label' => 'Hiện nút thanh toán / trả góp Bảo Kim ở trang sản phẩm và giỏ hàng',
                'options' => null,
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Cache::forget('settings.public');
        Cache::forget('settings.all');
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
        Cache::forget('settings.public');
        Cache::forget('settings.all');
    }
};
