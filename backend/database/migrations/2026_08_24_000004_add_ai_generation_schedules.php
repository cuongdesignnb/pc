<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generation_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 500);
            $table->text('keywords')->nullable();
            $table->enum('type', ['article', 'product_description', 'category_description', 'seo'])->default('article');
            $table->enum('tone', ['professional', 'casual', 'luxury'])->default('professional');
            $table->enum('length', ['short', 'medium', 'long'])->default('medium');
            $table->boolean('full_article')->default(true);
            $table->boolean('with_images')->default(false);
            $table->unsignedTinyInteger('image_count')->default(0);
            $table->boolean('auto_publish')->default(false);
            $table->foreignId('category_id')->nullable()->constrained('post_categories')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->enum('status', ['pending', 'processing', 'done', 'failed'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamp('scheduled_at');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('article_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['status', 'locked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_schedules');
    }
};
