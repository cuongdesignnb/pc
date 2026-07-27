<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_an_image_that_is_converted_to_webp(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware();

        $admin = User::factory()->create(['role' => 'admin']);
        $image = UploadedFile::fake()->image('sample.png', 10, 10);

        $response = $this->actingAs($admin)->post('/admin/media/upload', [
            'files' => [$image],
            'folder' => '/',
        ]);

        $response->assertRedirect();

        $media = Media::query()->sole();

        $this->assertSame('image/webp', $media->mime_type);
        $this->assertStringEndsWith('.webp', $media->path);
        Storage::disk('public')->assertExists($media->path);
    }
}
