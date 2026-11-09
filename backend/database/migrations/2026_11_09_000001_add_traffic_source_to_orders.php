<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('utm_source', 150)->nullable()->after('referral_discount');
            $table->string('utm_campaign', 150)->nullable()->after('utm_source');
            $table->string('utm_content', 150)->nullable()->after('utm_campaign');
            $table->string('video_code', 100)->nullable()->after('utm_content');
            $table->index('utm_source');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['utm_source']);
            $table->dropColumn(['utm_source', 'utm_campaign', 'utm_content', 'video_code']);
        });
    }
};
