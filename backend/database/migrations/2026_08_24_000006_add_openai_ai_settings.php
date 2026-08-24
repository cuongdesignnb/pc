<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['key' => 'openai_api_key', 'value' => '', 'group' => 'ai', 'type' => 'password', 'label' => 'API key AI nội dung (OpenAI-compatible)', 'is_public' => false],
            ['key' => 'openai_base_url', 'value' => 'https://modelapi.vn/v1', 'group' => 'ai', 'type' => 'text', 'label' => 'Base URL AI nội dung', 'is_public' => false],
            ['key' => 'openai_wire_api', 'value' => 'chat_completions', 'group' => 'ai', 'type' => 'select', 'options' => json_encode(['choices' => ['chat_completions', 'responses']]), 'label' => 'Kiểu API AI nội dung', 'is_public' => false],
            ['key' => 'openai_model', 'value' => 'gpt-5.5', 'group' => 'ai', 'type' => 'text', 'label' => 'Model AI nội dung', 'is_public' => false],
            ['key' => 'openai_image_api_key', 'value' => '', 'group' => 'ai', 'type' => 'password', 'label' => 'API key AI hình ảnh (OpenAI chính hãng)', 'is_public' => false],
            ['key' => 'openai_image_base_url', 'value' => 'https://api.openai.com/v1', 'group' => 'ai', 'type' => 'text', 'label' => 'Base URL AI hình ảnh', 'is_public' => false],
            ['key' => 'openai_image_model', 'value' => 'gpt-image-2', 'group' => 'ai', 'type' => 'text', 'label' => 'Model AI hình ảnh', 'is_public' => false],
        ] as $setting) {
            DB::table('settings')->updateOrInsert(['key' => $setting['key']], $setting + ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'openai_api_key', 'openai_base_url', 'openai_wire_api', 'openai_model',
            'openai_image_api_key', 'openai_image_base_url', 'openai_image_model',
        ])->delete();
    }
};
