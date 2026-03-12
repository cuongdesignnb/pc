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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('title');
            $table->string('url')->nullable();                     // custom URL
            $table->string('type')->default('custom');             // custom, category, page
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('icon')->nullable();                    // emoji or icon class
            $table->string('badge_text')->nullable();              // e.g. "HOT", "Mới"
            $table->string('badge_color')->nullable();             // e.g. "red", "green"
            $table->string('css_class')->nullable();
            $table->string('target')->default('_self');            // _self, _blank
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_mega')->default(false);            // render as mega menu dropdown
            $table->unsignedTinyInteger('mega_columns')->default(4); // columns in mega dropdown
            $table->string('description')->nullable();             // shown under title in mega menu
            $table->string('image')->nullable();                   // optional image for mega item
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
