<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_product_content_campaigns', function (Blueprint $table) {
            $table->boolean('include_product_images')->default(true)->after('append_contact_footer');
        });
    }

    public function down(): void
    {
        Schema::table('ai_product_content_campaigns', function (Blueprint $table) {
            $table->dropColumn('include_product_images');
        });
    }
};
