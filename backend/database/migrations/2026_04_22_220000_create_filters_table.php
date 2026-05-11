<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bảng nhóm bộ lọc (CPU, RAM, Khoảng giá...)
        Schema::create('filters', function (Blueprint $table) {
            $table->id();
            $table->string('name');              // VD: "CPU", "RAM", "Khoảng giá"
            $table->string('slug')->unique();     // VD: "cpu", "ram", "khoang-gia"
            $table->enum('type', ['checkbox', 'price_range'])->default('checkbox');
            $table->enum('match_field', [
                'specifications_text',  // LIKE search trong specifications_text
                'product_name',         // LIKE search trong tên sản phẩm
                'brand',                // Match brand slug
                'price',                // Price range (dùng price_min/price_max)
            ])->default('specifications_text');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Bảng giá trị của mỗi bộ lọc
        Schema::create('filter_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filter_id')->constrained()->cascadeOnDelete();
            $table->string('label');              // VD: "Intel Core i5", "16GB"
            $table->string('slug');               // VD: "intel-core-i5", "16gb" (dùng cho URL)
            $table->string('match_value')->nullable(); // Pattern tìm kiếm: "Core i5", brand slug, etc.
            $table->decimal('price_min', 15, 0)->nullable(); // Cho bộ lọc giá
            $table->decimal('price_max', 15, 0)->nullable(); // Cho bộ lọc giá
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['filter_id', 'slug']);
            $table->index(['filter_id', 'is_active', 'sort_order']);
        });

        // Bảng pivot: gán bộ lọc vào danh mục
        Schema::create('category_filter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('filter_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'filter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_filter');
        Schema::dropIfExists('filter_values');
        Schema::dropIfExists('filters');
    }
};
