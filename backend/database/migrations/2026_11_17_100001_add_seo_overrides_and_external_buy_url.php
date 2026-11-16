<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - products/posts/pages.meta_robots: per-row robots directive (null = the
 *   default "index,follow"); noindex rows leave the sitemaps.
 * - products/posts/pages.canonical_url: optional canonical override (an https
 *   URL or a path on this site).
 * - products.external_buy_url / external_buy_label: "Link mua ngoài". When set
 *   the product page shows one button to that page instead of cart/buy.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'posts', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (! Schema::hasColumn($table, 'meta_robots')) {
                    $blueprint->string('meta_robots', 30)->nullable()->after('meta_description');
                }
                if (! Schema::hasColumn($table, 'canonical_url')) {
                    $blueprint->string('canonical_url', 2048)->nullable()->after('meta_description');
                }
            });
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'external_buy_url')) {
                $table->string('external_buy_url', 2048)->nullable()->after('instant_key_url');
            }
            if (! Schema::hasColumn('products', 'external_buy_label')) {
                $table->string('external_buy_label', 60)->nullable()->after('instant_key_url');
            }
        });
    }

    public function down(): void
    {
        foreach (['products', 'posts', 'pages'] as $table) {
            foreach (['meta_robots', 'canonical_url'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
                }
            }
        }
        foreach (['external_buy_url', 'external_buy_label'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                Schema::table('products', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
