<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('sku', 100)->nullable();
            $table->decimal('price', 15, 0);
            $table->decimal('sale_price', 15, 0)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_active', 'sort_order']);
            $table->unique('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
