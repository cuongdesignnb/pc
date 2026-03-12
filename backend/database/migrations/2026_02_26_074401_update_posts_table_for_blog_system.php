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
        Schema::table('posts', function (Blueprint $table) {
            // Drop old columns
            $table->dropColumn(['category', 'is_published', 'views_count']);
            
            // Add new columns
            $table->foreignId('post_category_id')->nullable()->after('user_id')->constrained('post_categories')->nullOnDelete();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->after('body');
            $table->unsignedBigInteger('view_count')->default(0)->after('status');
            $table->boolean('is_featured')->default(false)->after('view_count');
            
            // Indexes
            $table->index('status');
            $table->index('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Restore old columns
            $table->string('category')->nullable()->after('featured_image');
            $table->boolean('is_published')->default(false)->after('category');
            $table->unsignedInteger('views_count')->default(0)->after('meta_description');
            
            // Drop new columns
            $table->dropForeign(['post_category_id']);
            $table->dropColumn(['post_category_id', 'status', 'view_count', 'is_featured']);
        });
    }
};
