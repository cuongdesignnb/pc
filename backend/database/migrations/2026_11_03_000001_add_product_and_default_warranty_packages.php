<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Warranty packages can now be set on a single product and as shop-wide
 * defaults (Cài đặt → Bảo hành), besides categories. A package adds a fixed
 * amount or a percentage of the price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->json('warranty_packages')->nullable()->after('warranty_months');
        });

        if (! DB::table('settings')->where('key', 'warranty_default_packages')->exists()) {
            DB::table('settings')->insert([
                'key' => 'warranty_default_packages',
                'value' => '[]',
                'group' => 'warranty',
                'type' => 'json',
                'options' => null,
                'label' => 'Gói bảo hành mặc định cho mọi sản phẩm (dùng khi sản phẩm và danh mục chưa có gói riêng)',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Cache::forget('settings.public');
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'warranty_default_packages')->delete();
        Cache::forget('settings.public');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('warranty_packages'));
    }
};
