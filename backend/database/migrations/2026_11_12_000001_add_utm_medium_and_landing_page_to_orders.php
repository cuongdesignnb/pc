<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the order came from, next to utm_source / utm_campaign / utm_content:
 * utm_medium and the first page the customer opened on the website.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'utm_medium')) {
                $table->string('utm_medium', 150)->nullable()->after('utm_source');
            }
            if (! Schema::hasColumn('orders', 'landing_page')) {
                $table->string('landing_page', 500)->nullable()->after('video_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['utm_medium', 'landing_page']);
        });
    }
};
