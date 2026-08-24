<?php

namespace App\Services\Ai;

use App\Models\Setting;

class AiConfigurationResolver
{
    public function content(): array
    {
        return $this->resolve('content', [
            'api_key' => ['openai_api_key', config('ai.content.api_key')],
            'base_url' => ['openai_base_url', config('ai.content.base_url')],
            'wire_api' => ['openai_wire_api', config('ai.content.wire_api')],
            'model' => ['openai_model', config('ai.content.model')],
            'reasoning_effort' => ['openai_reasoning_effort', config('ai.content.reasoning_effort')],
            'max_tokens' => ['openai_max_tokens', config('ai.content.max_tokens')],
            'timeout' => ['openai_timeout_seconds', config('ai.content.timeout')],
        ]);
    }

    public function image(): array
    {
        return $this->resolve('image', [
            'api_key' => ['openai_image_api_key', config('ai.image.api_key')],
            'base_url' => ['openai_image_base_url', config('ai.image.base_url')],
            'model' => ['openai_image_model', config('ai.image.model')],
            'quality' => ['openai_image_quality', config('ai.image.quality')],
            'timeout' => ['openai_image_timeout_seconds', config('ai.image.timeout')],
            'max_bytes' => ['openai_image_max_bytes', config('ai.image.max_bytes')],
        ]);
    }

    public function contentConfigured(): bool
    {
        return $this->content()['api_key'] !== '';
    }

    private function resolve(string $name, array $values): array
    {
        $resolved = [];
        foreach ($values as $key => [$settingKey, $fallback]) {
            $setting = Setting::query()->where('key', $settingKey)->value('value');
            $resolved[$key] = is_string($setting) && trim($setting) !== ''
                ? trim($setting)
                : $fallback;
        }

        $resolved['base_url'] = rtrim((string) $resolved['base_url'], '/');
        if (! in_array($resolved['wire_api'] ?? null, ['chat_completions', 'responses'], true)) {
            $resolved['wire_api'] = 'chat_completions';
        }

        return $resolved;
    }
}
