<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\AiArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiArticleProviderRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.content.api_key', 'third-party-key');
        config()->set('ai.content.base_url', 'https://modelapi.vn/v1');
        config()->set('ai.content.wire_api', 'chat_completions');
        config()->set('ai.content.model', 'gpt-5.5');
        config()->set('ai.image.api_key', 'official-image-key');
        config()->set('ai.image.base_url', 'https://api.openai.com/v1');
        // A legacy official key must no longer be used for article text.
        Setting::set('chatgpt_api_key', 'legacy-official-key');
    }

    public function test_article_text_uses_third_party_content_provider(): void
    {
        Http::fake([
            'https://modelapi.vn/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'title' => 'Laptop gaming 2026',
                    'slug' => 'laptop-gaming-2026',
                    'body' => '<h2>Chọn máy</h2>',
                    'image_prompt' => 'gaming laptop on a desk',
                ], JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);

        $article = (new AiArticleService)->generateArticle('laptop gaming', 'chatgpt', 500);

        $this->assertSame('Laptop gaming 2026', $article['title']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://modelapi.vn/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer third-party-key')
            && $request['model'] === 'gpt-5.5');
    }

    public function test_article_image_uses_official_image_key(): void
    {
        Storage::fake('public');
        $png = base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        Http::fake([
            'https://api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => $png]]]),
        ]);

        $url = (new AiArticleService)->generateImage('gaming laptop on a desk', 'chatgpt');

        $this->assertNotNull($url);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/images/generations'
            && $request->hasHeader('Authorization', 'Bearer official-image-key'));
    }

    public function test_settings_page_hides_retired_chatgpt_keys(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Index')
                ->where('settings.ai', function ($items) {
                    $keys = collect($items)->pluck('key');

                    return ! $keys->contains('chatgpt_api_key')
                        && ! $keys->contains('chatgpt_model')
                        && $keys->contains('gemini_api_key')
                        && $keys->contains('openai_image_api_key');
                }));
    }
}
