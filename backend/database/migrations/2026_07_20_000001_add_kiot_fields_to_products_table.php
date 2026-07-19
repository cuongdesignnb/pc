<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode')->nullable()->index();
            $table->string('inventory_source')->default('local')->index();
            $table->unsignedBigInteger('kiot_product_id')->nullable()->index();
            $table->string('kiot_sync_status')->default('unmapped')->index();
            $table->boolean('kiot_sellable')->default(false)->index();
            $table->boolean('kiot_has_serial')->default(false);
            $table->integer('kiot_physical_quantity')->nullable();
            $table->integer('kiot_reserved_quantity')->nullable();
            $table->integer('kiot_available_quantity')->nullable();
            $table->decimal('kiot_retail_price', 15, 2)->nullable();
            $table->timestamp('kiot_remote_updated_at')->nullable();
            $table->timestamp('kiot_synced_at')->nullable()->index();
            $table->string('kiot_sync_error_code')->nullable();
            $table->text('kiot_sync_error_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'barcode', 'inventory_source', 'kiot_product_id', 'kiot_sync_status',
                'kiot_sellable', 'kiot_has_serial', 'kiot_physical_quantity',
                'kiot_reserved_quantity', 'kiot_available_quantity', 'kiot_retail_price',
                'kiot_remote_updated_at', 'kiot_synced_at', 'kiot_sync_error_code',
                'kiot_sync_error_message',
            ]);
        });
    }
};
