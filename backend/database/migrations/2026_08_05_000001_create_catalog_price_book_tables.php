<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_price_books', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->unsignedBigInteger('remote_price_book_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->char('currency', 3)->default('VND');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamp('provider_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->timestamps();
            $table->unique(['provider', 'remote_price_book_id'], 'catalog_price_books_provider_remote_unique');
        });

        Schema::create('catalog_product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('price_book_id')->nullable()->constrained('catalog_price_books')->restrictOnDelete();
            $table->string('price_source', 32);
            $table->unsignedBigInteger('price')->default(0);
            $table->char('currency', 3)->default('VND');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamp('provider_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->char('checksum', 64);
            $table->timestamps();
            $table->unique(['product_id', 'price_source', 'price_book_id'], 'catalog_product_prices_source_unique');
            $table->index(['price_book_id', 'price']);
        });

        Schema::create('catalog_channel_price_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 32)->unique();
            $table->string('price_source', 64)->default('retail_price');
            $table->foreignId('price_book_id')->nullable()->constrained('catalog_price_books')->restrictOnDelete();
            $table->string('fallback_policy', 32)->default('none');
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('configured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('configured_at')->nullable();
            $table->timestamps();
            $table->index(['channel', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_channel_price_settings');
        Schema::dropIfExists('catalog_product_prices');
        Schema::dropIfExists('catalog_price_books');
    }
};
