<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\AiConfigurationResolver;
use App\Services\Ai\AiContentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiConfigurationSecretsTest extends TestCase
{
    use RefreshDatabase;

    public function test_keys_saved_from_admin_settings_are_decrypted_before_use(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put('/admin/settings', ['settings' => [
                ['key' => 'openai_api_key', 'value' => 'sk-content-plain'],
                ['key' => 'openai_image_api_key', 'value' => 'sk-image-plain'],
            ]])
            ->assertSessionHasNoErrors();

        // Stored encrypted at rest...
        $stored = DB::table('settings')->where('key', 'openai_api_key')->value('value');
        $this->assertNotSame('sk-content-plain', $stored);

        // ...but resolved in plain text for the provider.
        $resolver = app(AiConfigurationResolver::class);
        $this->assertSame('sk-content-plain', $resolver->content()['api_key']);
        $this->assertSame('sk-image-plain', $resolver->image()['api_key']);
    }

    public function test_content_provider_sends_decrypted_key_as_bearer_token(): void
    {
        Setting::set('openai_api_key', 'sk-content-plain');
        Setting::set('openai_base_url', 'https://modelapi.vn/v1');
        Setting::set('openai_wire_api', 'chat_completions');
        Http::fake(['https://modelapi.vn/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '{"content":"<p>ok</p>"}']]],
        ])]);

        app(AiContentProvider::class)->complete('system', 'prompt');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-content-plain'));
    }

    public function test_blank_setting_falls_back_to_environment_config(): void
    {
        config()->set('ai.content.api_key', 'env-key');
        Setting::set('openai_api_key', '');

        $this->assertSame('env-key', app(AiConfigurationResolver::class)->content()['api_key']);
    }
}
