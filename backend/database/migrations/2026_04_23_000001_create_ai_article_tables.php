<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // AI Article Batches
        Schema::create('ai_article_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('keywords');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('ai_provider')->default('chatgpt'); // chatgpt or gemini
            $table->foreignId('default_category_id')->nullable()->constrained('post_categories')->nullOnDelete();
            $table->timestamp('schedule_at')->nullable();
            $table->boolean('auto_publish')->default(false);
            $table->integer('total_items')->default(0);
            $table->integer('completed_items')->default(0);
            $table->timestamps();
        });

        // AI Article Batch Items
        Schema::create('ai_article_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('ai_article_batches')->cascadeOnDelete();
            $table->string('keyword');
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->enum('status', ['pending', 'generating', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        // AI Settings
        $settings = [
            ['key' => 'chatgpt_api_key', 'value' => '', 'group' => 'ai', 'type' => 'text', 'label' => 'ChatGPT API Key', 'is_public' => false],
            ['key' => 'chatgpt_model', 'value' => 'gpt-4o-mini', 'group' => 'ai', 'type' => 'text', 'label' => 'ChatGPT Model', 'is_public' => false],
            ['key' => 'gemini_api_key', 'value' => '', 'group' => 'ai', 'type' => 'text', 'label' => 'Gemini API Key', 'is_public' => false],
            ['key' => 'gemini_model', 'value' => 'gemini-2.5-flash', 'group' => 'ai', 'type' => 'text', 'label' => 'Gemini Model', 'is_public' => false],
            ['key' => 'ai_word_count', 'value' => '1500', 'group' => 'ai', 'type' => 'number', 'label' => 'So tu moi bai', 'is_public' => false],
            ['key' => 'ai_language', 'value' => 'vi', 'group' => 'ai', 'type' => 'text', 'label' => 'Ngon ngu mac dinh', 'is_public' => false],
        ];

        foreach ($settings as $s) {
            DB::table('settings')->updateOrInsert(
                ['key' => $s['key']],
                $s
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_article_batch_items');
        Schema::dropIfExists('ai_article_batches');
        DB::table('settings')->whereIn('key', [
            'chatgpt_api_key', 'chatgpt_model', 'gemini_api_key', 'gemini_model', 'ai_word_count', 'ai_language',
        ])->delete();
    }
};
