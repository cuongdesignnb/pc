<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - "Khuyến mại" on the product page: one gift/offer per line, set on a
 *   category (inherited by its products and sub-categories) or on a product.
 * - Robots and Canonical for categories, like products and posts already have.
 * - Link for the extra line of the footer "Thanh toán" column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'promotion_text')) {
                $table->text('promotion_text')->nullable();
            }
            if (! Schema::hasColumn('categories', 'meta_robots')) {
                $table->string('meta_robots', 32)->nullable();
            }
            if (! Schema::hasColumn('categories', 'canonical_url')) {
                $table->string('canonical_url', 2048)->nullable();
            }
        });
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'promotion_text')) {
                $table->text('promotion_text')->nullable();
            }
        });

        if (! DB::table('settings')->where('key', 'payment_footer_extra_url')->exists()) {
            DB::table('settings')->insert([
                'key' => 'payment_footer_extra_url',
                'value' => '/mua-laptop-tra-gop',
                'type' => 'text',
                'group' => 'payment',
                'label' => 'Link cho dòng thêm ở ô "Thanh toán" chân trang (để trống = không có link)',
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
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['promotion_text', 'meta_robots', 'canonical_url']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('promotion_text');
        });
        DB::table('settings')->where('key', 'payment_footer_extra_url')->delete();
        Cache::forget('settings.public');
        Cache::forget('settings.all');
    }
};
