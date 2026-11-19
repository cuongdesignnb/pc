<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Bank account that receives SePay transfers (VietQR code, account number,
 * holder). Checkout offers "Chuyển khoản" only when all three are filled, but
 * the rows only existed in the seeder, so production had no field to fill in
 * and showed COD alone. Existing values are kept; new rows start empty.
 */
return new class extends Migration
{
    private const SETTINGS = [
        'payment_bank_name' => 'Mã ngân hàng nhận chuyển khoản (VietQR, ví dụ MB, VCB, TCB)',
        'payment_bank_account' => 'Số tài khoản nhận chuyển khoản',
        'payment_bank_holder' => 'Tên chủ tài khoản (viết hoa, không dấu)',
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as $key => $label) {
            if (DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->where('key', $key)->update(['group' => 'payment', 'label' => $label]);

                continue;
            }
            DB::table('settings')->insert([
                'key' => $key,
                'value' => '',
                'type' => 'text',
                'group' => 'payment',
                'label' => $label,
                'options' => null,
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->forgetCache();
    }

    public function down(): void
    {
        // Values typed in admin are the shop's bank details; keep them.
        $this->forgetCache();
    }

    private function forgetCache(): void
    {
        foreach (array_keys(self::SETTINGS) as $key) {
            Cache::forget("setting.{$key}");
        }
        Cache::forget('settings.public');
        Cache::forget('settings.all');
    }
};
