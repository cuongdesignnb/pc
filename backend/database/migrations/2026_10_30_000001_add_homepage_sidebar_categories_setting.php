<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Categories listed in the homepage "Danh mục nổi bật" sidebar. Empty
     * means every top-level category, in admin sort order.
     */
    public function up(): void
    {
        if (DB::table('settings')->where('key', 'homepage_sidebar_category_slugs')->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'key' => 'homepage_sidebar_category_slugs',
            'value' => '[]',
            'group' => 'homepage',
            'type' => 'json',
            'label' => 'Danh mục ở menu dọc bên trái trang chủ',
            'is_public' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Settings are user-editable data; keep them on rollback.
    }
};
