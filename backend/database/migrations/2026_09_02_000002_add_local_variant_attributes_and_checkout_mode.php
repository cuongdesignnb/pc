<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->json('attributes')->nullable()->after('name');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('checkout_mode', 20)->default('cart')->after('payment_method');
            $table->index('checkout_mode');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['checkout_mode']);
            $table->dropColumn('checkout_mode');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('attributes');
        });
    }
};
