<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [{months, price}] the customer can pick from on products in this category.
        Schema::table('categories', function (Blueprint $table): void {
            $table->json('warranty_packages')->nullable()->after('technical_heading');
        });
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('warranty_months')->nullable()->after('variant_id');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('warranty_price', 15, 0)->default(0)->after('warranty_months');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('warranty_price'));
        Schema::table('cart_items', fn (Blueprint $table) => $table->dropColumn('warranty_months'));
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('warranty_packages'));
    }
};
