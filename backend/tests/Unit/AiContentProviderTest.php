<?php

namespace Tests\Unit;

use App\Exceptions\AiProviderException;
use App\Services\Ai\AiConfigurationResolver;
use App\Services\Ai\AiContentProvider;
use App\Services\Ai\AiContentSanitizer;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class AiContentProviderTest extends TestCase
{
    public function test_chat_completions_response_is_parsed_and_html_is_sanitized(): void
    {
        Http::fake([
            'https://modelapi.vn/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'title' => 'Hướng dẫn chọn laptop',
                    'excerpt' => 'Tóm tắt',
                    'content' => '<h2>Chọn máy</h2><script>alert(1)</script><p><strong>Thông tin</strong></p>',
                    'meta_title' => 'Hướng dẫn chọn laptop',
                    'meta_desc' => 'Mô tả SEO',
                    'tags' => ['laptop'],
                ], JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);

        $resolver = Mockery::mock(AiConfigurationResolver::class);
        $resolver->shouldReceive('content')->once()->andReturn([
            'api_key' => 'content-secret', 'base_url' => 'https://modelapi.vn/v1', 'wire_api' => 'chat_completions',
            'model' => 'test-model', 'reasoning_effort' => 'high', 'max_tokens' => 1000, 'timeout' => 10,
        ]);
        $provider = new AiContentProvider($resolver, new AiContentSanitizer);

        $result = $provider->generate($this->input());

        $this->assertSame('Hướng dẫn chọn laptop', $result['title']);
        $this->assertStringNotContainsString('<script', $result['content']);
        $this->assertStringContainsString('<strong>Thông tin</strong>', $result['content']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer content-secret'));
    }

    public function test_responses_output_text_is_supported(): void
    {
        Http::fake(['https://modelapi.vn/v1/responses' => Http::response([
            'output_text' => json_encode(['content' => '<p>Nội dung</p>', 'meta_desc' => 'SEO'], JSON_UNESCAPED_UNICODE),
        ])]);
        $resolver = Mockery::mock(AiConfigurationResolver::class);
        $resolver->shouldReceive('content')->andReturn([
            'api_key' => 'secret', 'base_url' => 'https://modelapi.vn/v1', 'wire_api' => 'responses',
            'model' => 'test-model', 'reasoning_effort' => 'high', 'max_tokens' => 1000, 'timeout' => 10,
        ]);

        $result = (new AiContentProvider($resolver, new AiContentSanitizer))->generate($this->input());
        $this->assertSame('<p>Nội dung</p>', $result['content']);
        Http::assertSent(fn ($request) => $request->url() === 'https://modelapi.vn/v1/responses'
            && $request['store'] === false && isset($request['max_output_tokens']));
    }

    public function test_missing_key_fails_before_provider_call(): void
    {
        Http::fake();
        $resolver = Mockery::mock(AiConfigurationResolver::class);
        $resolver->shouldReceive('content')->andReturn([
            'api_key' => '', 'base_url' => 'https://modelapi.vn/v1', 'wire_api' => 'chat_completions',
            'model' => 'test-model', 'reasoning_effort' => 'high', 'max_tokens' => 1000, 'timeout' => 10,
        ]);

        $this->expectException(AiProviderException::class);
        (new AiContentProvider($resolver, new AiContentSanitizer))->generate($this->input());
        Http::assertNothingSent();
    }

    public function test_http_base_url_is_rejected(): void
    {
        $resolver = Mockery::mock(AiConfigurationResolver::class);
        $resolver->shouldReceive('content')->andReturn([
            'api_key' => 'secret', 'base_url' => 'http://modelapi.vn/v1', 'wire_api' => 'chat_completions',
            'model' => 'test-model', 'reasoning_effort' => 'high', 'max_tokens' => 1000, 'timeout' => 10,
        ]);

        $this->expectException(AiProviderException::class);
        (new AiContentProvider($resolver, new AiContentSanitizer))->generate($this->input());
    }

    private function input(): array
    {
        return [
            'topic' => 'Laptop cho sinh viên', 'type' => 'article', 'keywords' => 'laptop học tập',
            'tone' => 'professional', 'length' => 'medium', 'existing_content' => '',
        ];
    }
}
