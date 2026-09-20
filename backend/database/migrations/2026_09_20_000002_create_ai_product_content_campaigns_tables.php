<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_product_content_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->json('filters')->nullable();
            $table->enum('mode', ['draft', 'publish'])->default('draft');
            $table->enum('technical_heading', ['auto', 'configuration', 'specifications'])->default('auto');
            $table->boolean('use_web_research')->default(false);
            $table->boolean('append_contact_footer')->default(false);
            $table->unsignedSmallInteger('max_items')->default(100);
            $table->enum('status', ['pending', 'processing', 'completed', 'partial_failed', 'cancelled'])->default('pending');
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('pending_items')->default(0);
            $table->unsignedInteger('draft_items')->default(0);
            $table->unsignedInteger('applied_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->unsignedInteger('review_items')->default(0);
            $table->timestamp('scheduled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('ai_product_content_campaign_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('ai_product_content_campaigns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->json('source_snapshot');
            $table->json('generated_payload')->nullable();
            $table->json('research_sources')->nullable();
            $table->enum('status', ['pending', 'processing', 'draft', 'applied', 'needs_review', 'failed', 'skipped'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'product_id']);
            $table->index(['status', 'locked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_product_content_campaign_items');
        Schema::dropIfExists('ai_product_content_campaigns');
    }
};
