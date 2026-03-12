<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_email')->nullable()->after('shipping_phone');
            $table->string('shipping_city')->nullable()->after('shipping_address');
            $table->string('shipping_district')->nullable()->after('shipping_city');
            $table->string('shipping_ward')->nullable()->after('shipping_district');
            $table->string('payment_method')->default('sepay')->after('notes');
        });

        // Update payment_status enum to include 'pending' state
        // Change enum to string for more flexibility
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status_new')->default('unpaid')->after('payment_status');
        });

        // Copy data
        \DB::statement("UPDATE orders SET payment_status_new = payment_status");

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('payment_status_new', 'payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_email',
                'shipping_city',
                'shipping_district',
                'shipping_ward',
                'payment_method',
            ]);
        });
    }
};
