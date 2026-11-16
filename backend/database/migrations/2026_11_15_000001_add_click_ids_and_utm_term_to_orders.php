<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * More about where the order came from: utm_term and the ad click ids Google
 * (gclid) and Facebook (fbclid) add to links, so ads can be matched to orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'utm_term')) {
                $table->string('utm_term', 150)->nullable()->after('utm_content');
            }
            if (! Schema::hasColumn('orders', 'gclid')) {
                $table->string('gclid', 255)->nullable()->after('landing_page');
            }
            if (! Schema::hasColumn('orders', 'fbclid')) {
                $table->string('fbclid', 255)->nullable()->after('gclid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['utm_term', 'gclid', 'fbclid']);
        });
    }
};
