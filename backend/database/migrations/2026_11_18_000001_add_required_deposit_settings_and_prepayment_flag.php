<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Bắt buộc đặt cọc với đơn giao đi tỉnh": orders delivered outside the exempt
 * provinces pay a deposit by SePay before the shop handles them ("Chờ cọc"),
 * and bank-transfer orders reach KIOT only once the money is confirmed.
 * The switch is off, so nothing changes until the shop turns it on.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'payment_deposit_required', 'value' => '0', 'type' => 'boolean',
            'label' => 'Bắt buộc đặt cọc với đơn giao đi tỉnh (khách phải chuyển khoản cọc trước, không chọn được COD)'],
        ['key' => 'payment_deposit_exempt_provinces', 'value' => '["01"]', 'type' => 'json',
            'label' => 'Tỉnh miễn cọc (không bắt buộc đặt cọc)'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'requires_prepayment')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->boolean('requires_prepayment')->default(false)->after('deposit_amount');
            });
        }

        foreach (self::SETTINGS as $setting) {
            if (DB::table('settings')->where('key', $setting['key'])->exists()) {
                continue;
            }
            DB::table('settings')->insert($setting + [
                'group' => 'payment',
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

        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('requires_prepayment'));
    }
};
