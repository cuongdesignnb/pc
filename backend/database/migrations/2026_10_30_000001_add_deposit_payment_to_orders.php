<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bank-transfer orders can be paid in full or with a deposit first. The order
 * keeps what the customer chose, the deposit asked for and the money SePay
 * has actually confirmed, so admin sees "Đã cọc" vs "Đã thanh toán".
 */
return new class extends Migration
{
    private const SETTINGS = [
        [
            'key' => 'payment_deposit_enabled',
            'value' => '0',
            'type' => 'boolean',
            'options' => null,
            'label' => 'Cho phép đặt cọc khi chuyển khoản',
        ],
        [
            'key' => 'payment_deposit_type',
            'value' => 'percent',
            'type' => 'select',
            'options' => ['choices' => ['percent', 'fixed']],
            'label' => 'Kiểu tiền cọc (percent = % tổng đơn, fixed = số tiền cố định)',
        ],
        [
            'key' => 'payment_deposit_value',
            'value' => '10',
            'type' => 'number',
            'options' => null,
            'label' => 'Mức cọc (số % hoặc số tiền VNĐ, theo kiểu ở trên)',
        ],
    ];

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_option', 20)->nullable()->after('payment_method');
            $table->decimal('deposit_amount', 15, 0)->nullable()->after('payment_option');
            $table->decimal('amount_paid', 15, 0)->default(0)->after('deposit_amount');
            $table->timestamp('deposit_paid_at')->nullable()->after('paid_at');
        });

        // Orders paid before this change were paid in full.
        DB::table('orders')->where('payment_status', 'paid')->update(['amount_paid' => DB::raw('total')]);
        DB::table('orders')->where('payment_method', 'sepay')->update(['payment_option' => 'full']);

        foreach (self::SETTINGS as $setting) {
            if (DB::table('settings')->where('key', $setting['key'])->exists()) {
                continue;
            }
            DB::table('settings')->insert([
                'key' => $setting['key'],
                'value' => $setting['value'],
                'group' => 'payment',
                'type' => $setting['type'],
                'options' => $setting['options'] ? json_encode($setting['options']) : null,
                'label' => $setting['label'],
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Cache::forget('settings.public');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::SETTINGS, 'key'))->delete();
        Cache::forget('settings.public');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_option', 'deposit_amount', 'amount_paid', 'deposit_paid_at']);
        });
    }
};
