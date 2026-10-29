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

    public function test_inertia_admin_can_upload_an_image_and_receive_a_redirect(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware();

        $admin = User::factory()->create(['role' => 'admin']);
        $image = UploadedFile::fake()->image('sample.png', 10, 10);

        $response = $this->actingAs($admin)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post('/admin/media/upload', [
                'files' => [$image],
                'folder' => '/',
            ]);

        $response->assertRedirect();

        $media = Media::query()->sole();

        $this->assertSame('image/webp', $media->mime_type);
        $this->assertStringEndsWith('.webp', $media->path);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_json_client_can_upload_an_image_and_receive_created_media(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware();

        $admin = User::factory()->create(['role' => 'admin']);
        $image = UploadedFile::fake()->image('sample.png', 10, 10);

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post('/admin/media/upload', [
                'files' => [$image],
                'folder' => '/',
            ]);

        $response->assertCreated()
            ->assertJsonPath('files.0.mime_type', 'image/webp');

        $media = Media::query()->sole();

        $this->assertStringEndsWith('.webp', $media->path);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_mp4_videos_up_to_50mb_are_accepted(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware();
        $admin = User::factory()->create(['role' => 'admin']);

        $video = UploadedFile::fake()->createWithContent('clip.mp4', $this->mp4Bytes(1024));
        $this->actingAs($admin)->withHeader('Accept', 'application/json')
            ->post('/admin/media/upload', ['files' => [$video], 'folder' => '/'])
            ->assertCreated()
            ->assertJsonPath('files.0.mime_type', 'video/mp4');

        $tooLarge = UploadedFile::fake()->createWithContent('big.mp4', $this->mp4Bytes(1024))->size(51201);
        $this->actingAs($admin)->withHeader('Accept', 'application/json')
            ->post('/admin/media/upload', ['files' => [$tooLarge], 'folder' => '/'])
            ->assertUnprocessable();
    }

    private function mp4Bytes(int $length): string
    {
        $ftyp = pack('N', 24).'ftypisom'.pack('N', 512).'isommp41';

        return str_pad($ftyp.pack('N', 8).'free', $length, "\0");
    }
}
