<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ai_product_content_campaigns', 'sections')) {
            Schema::table('ai_product_content_campaigns', function (Blueprint $table) {
                // Null keeps campaigns created before this column on their
                // original behaviour (description and SEO only).
                $table->json('sections')->nullable()->after('include_product_images');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_product_content_campaigns', 'sections')) {
            Schema::table('ai_product_content_campaigns', function (Blueprint $table) {
                $table->dropColumn('sections');
            });
        }
    }
};
