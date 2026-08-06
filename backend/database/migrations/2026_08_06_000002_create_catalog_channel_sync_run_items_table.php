<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_channel_sync_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_run_id')->constrained('catalog_channel_sync_runs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('external_id', 255);
            $table->string('action', 16);
            $table->string('eligibility_status', 16);
            $table->json('validation_errors_json')->nullable();
            $table->unsignedBigInteger('selected_price')->nullable();
            $table->string('price_source', 64)->nullable();
            $table->string('image_status', 32)->nullable();
            $table->string('result_status', 32)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['sync_run_id', 'product_id'], 'catalog_run_items_run_product_unique');
            $table->index(['sync_run_id', 'eligibility_status'], 'catalog_run_items_run_eligibility_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_channel_sync_run_items');
    }
};
