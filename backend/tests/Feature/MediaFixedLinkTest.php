<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaFixedLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_gets_a_fixed_link_that_survives_replacing_the_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->withHeader('Accept', 'application/json')->post('/admin/media/upload', [
            'files' => [UploadedFile::fake()->createWithContent('Driver WiFi Intel.pdf', '%PDF-1.4 version 1')],
            'folder' => '/',
        ])->assertCreated()->assertJsonPath('files.0.public_slug', 'driver-wifi-intel');

        $media = Media::query()->sole();
        $this->assertStringEndsWith('/files/driver-wifi-intel', $media->fixed_url);
        $oldPath = $media->path;

        $response = $this->get('/files/driver-wifi-intel')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('%PDF-1.4 version 1', $response->streamedContent());
        $this->assertStringContainsString('driver-wifi-intel.pdf', (string) $response->headers->get('Content-Disposition'));

        // A second file with the same name gets its own link.
        $this->actingAs($admin)->withHeader('Accept', 'application/json')->post('/admin/media/upload', [
            'files' => [UploadedFile::fake()->createWithContent('Driver WiFi Intel.pdf', '%PDF-1.4 other')],
        ])->assertCreated()->assertJsonPath('files.0.public_slug', 'driver-wifi-intel-2');

        // "Thay file": new content, same link, old file removed.
        $this->actingAs($admin)->withHeader('Accept', 'application/json')->post("/admin/media/{$media->id}/replace", [
            'file' => UploadedFile::fake()->createWithContent('driver-v2.pdf', '%PDF-1.4 version 2'),
        ])->assertOk()->assertJsonPath('media.public_slug', 'driver-wifi-intel');

        $media->refresh();
        $this->assertNotSame($oldPath, $media->path);
        Storage::disk('public')->assertMissing($oldPath);
        $this->assertSame('%PDF-1.4 version 2', $this->get('/files/driver-wifi-intel')->assertOk()->streamedContent());

        $this->get('/files/khong-co')->assertNotFound();
    }

    public function test_files_over_50_mb_are_refused(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $media = Media::create(['name' => 'Catalog', 'file_name' => 'catalog.pdf', 'path' => 'media/catalog.pdf', 'disk' => 'public', 'mime_type' => 'application/pdf', 'size' => 10, 'folder' => '/']);

        $this->actingAs($admin)->withHeader('Accept', 'application/json')->post('/admin/media/upload', [
            'files' => [UploadedFile::fake()->create('big.pdf', 51201, 'application/pdf')],
        ])->assertUnprocessable()->assertJsonValidationErrors('files.0');

        $this->actingAs($admin)->withHeader('Accept', 'application/json')->post("/admin/media/{$media->id}/replace", [
            'file' => UploadedFile::fake()->create('big.pdf', 51201, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        // Exactly 50 MB is accepted.
        $this->actingAs($admin)->withHeader('Accept', 'application/json')->post('/admin/media/upload', [
            'files' => [UploadedFile::fake()->create('ok.pdf', 51200, 'application/pdf')],
        ])->assertCreated();
    }
}
