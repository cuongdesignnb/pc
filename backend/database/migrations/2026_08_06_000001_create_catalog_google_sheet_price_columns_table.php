<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_google_sheet_price_columns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')
                ->nullable()
                ->constrained('catalog_channel_connections')
                ->nullOnDelete();
            $table->string('price_source', 64);
            $table->foreignId('price_book_id')->nullable()->constrained('catalog_price_books')->restrictOnDelete();
            $table->string('column_key', 100);
            $table->string('column_label', 160);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(
                ['connection_id', 'price_source', 'price_book_id'],
                'catalog_google_sheet_price_columns_source_unique',
            );
            $table->index(['connection_id', 'sort_order'], 'google_sheet_price_columns_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_google_sheet_price_columns');
    }
};
