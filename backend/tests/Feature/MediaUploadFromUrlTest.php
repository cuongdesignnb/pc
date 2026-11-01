<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Services\Media\RemoteImageDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadFromUrlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin']);
        // Resolve every test host to a public address instead of using DNS.
        $this->app->instance(RemoteImageDownloader::class, new class extends RemoteImageDownloader
        {
            protected function resolve(string $host): array
            {
                return $host === 'internal.example.com' ? ['10.0.0.5'] : ['93.184.216.34'];
            }
        });
    }

    public function test_images_are_downloaded_from_links_into_the_library(): void
    {
        Http::fake([
            'https://cdn.example.com/dell-latitude-7420.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
            'https://cdn.example.com/trang.html' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->actingAs($this->admin)->postJson('/admin/media/upload-url', [
            'urls' => ['https://cdn.example.com/dell-latitude-7420.png', 'https://cdn.example.com/trang.html'],
            'folder' => '/',
        ])->assertCreated()
            ->assertJsonCount(1, 'files')
            ->assertJsonPath('failed.0.url', 'https://cdn.example.com/trang.html');

        $media = Media::firstOrFail();
        $this->assertSame('dell-latitude-7420', $media->name);
        $this->assertStringStartsWith('image/', $media->mime_type);
        $this->assertSame(4, $media->width);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_internal_addresses_and_redirects_are_refused(): void
    {
        Http::fake([
            'https://cdn.example.com/chuyen-huong.jpg' => Http::response('', 302, ['Location' => 'http://169.254.169.254/']),
        ]);

        $this->actingAs($this->admin)->postJson('/admin/media/upload-url', [
            'urls' => ['http://127.0.0.1/a.png', 'https://internal.example.com/a.png', 'http://localhost/a.png', 'https://cdn.example.com/chuyen-huong.jpg'],
        ])->assertUnprocessable()->assertJsonCount(4, 'failed');

        Http::assertSentCount(1);
        $this->assertSame(0, Media::count());

        $this->actingAs($this->admin)->postJson('/admin/media/upload-url', ['urls' => ['ftp://cdn.example.com/a.png']])
            ->assertUnprocessable()->assertJsonValidationErrors(['urls.0']);
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(4, 3);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
