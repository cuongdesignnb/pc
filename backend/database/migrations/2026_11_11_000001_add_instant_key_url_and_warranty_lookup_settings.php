<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - products.instant_key_url: "Lấy key ngay" link for software licences.
 * - Warranty lookup page (/tra-cuu-bao-hanh): page note and the Cloudflare
 *   Turnstile site key (the secret stays in .env as TURNSTILE_SECRET_KEY).
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'warranty_lookup_note', 'value' => 'Nhập số serial / service tag in dưới đáy máy, hoặc số điện thoại mua hàng kèm 4 ký tự cuối của serial.', 'type' => 'textarea',
            'label' => 'Trang tra cứu bảo hành: dòng hướng dẫn'],
        ['key' => 'turnstile_site_key', 'value' => '', 'type' => 'text',
            'label' => 'Cloudflare Turnstile site key (captcha trang tra cứu bảo hành; secret đặt trong .env TURNSTILE_SECRET_KEY)'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('products', 'instant_key_url')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->string('instant_key_url', 2048)->nullable()->after('video_url');
            });
        }

        foreach (self::SETTINGS as $setting) {
            if (! DB::table('settings')->where('key', $setting['key'])->exists()) {
                DB::table('settings')->insert($setting + [
                    'group' => 'warranty',
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
        if (Schema::hasColumn('products', 'instant_key_url')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn('instant_key_url'));
        }
    }
};
