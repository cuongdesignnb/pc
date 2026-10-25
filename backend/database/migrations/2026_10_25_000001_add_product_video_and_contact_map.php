<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'video_url')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('video_url', 500)->nullable()->after('meta_description');
            });
        }

        DB::table('settings')->updateOrInsert(['key' => 'contact_map_embed_url'], [
            'value' => '',
            'group' => 'contact',
            'type' => 'textarea',
            'label' => 'Bản đồ Google Maps (trang Liên hệ)',
            'is_public' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'contact_map_embed_url')->delete();

        if (Schema::hasColumn('products', 'video_url')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('video_url');
            });
        }
    }
};
