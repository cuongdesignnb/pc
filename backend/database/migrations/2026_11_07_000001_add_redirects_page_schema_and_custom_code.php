<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - redirects: 301/302 rules managed in admin (SEO → Chuyển hướng), applied by
 *   the storefront before rendering. Seeds /gioi-thieu → /gioi-thieu-laptop-plus.
 * - pages.schema_json: JSON-LD added to one page (LocalBusiness, FAQPage…).
 * - products.kiot_ready_quantity: KIOT's ready-to-sell count (not under repair).
 * - custom head / body-end code boxes in Cài đặt → SEO.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['custom_head_code', 'Mã tuỳ chỉnh đầu trang (chèn vào <head>)'],
        ['custom_body_code', 'Mã tuỳ chỉnh cuối trang (chèn trước </body>)'],
    ];

    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('source_path', 500)->unique();
            $table->string('target', 1000);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->longText('schema_json')->nullable()->after('meta_description');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('kiot_ready_quantity')->nullable()->after('kiot_available_quantity');
        });

        DB::table('redirects')->insert([
            'source_path' => '/gioi-thieu',
            'target' => '/gioi-thieu-laptop-plus',
            'status_code' => 301,
            'is_active' => true,
            'note' => 'Trang giới thiệu cũ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::SETTINGS as [$key, $label]) {
            if (DB::table('settings')->where('key', $key)->exists()) {
                continue;
            }
            DB::table('settings')->insert([
                'key' => $key, 'value' => '', 'group' => 'seo', 'type' => 'textarea', 'options' => null,
                'label' => $label, 'is_public' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Cache::forget('settings.public');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::SETTINGS, 0))->delete();
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('kiot_ready_quantity'));
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('schema_json'));
        Schema::dropIfExists('redirects');
    }
};
