<?php

namespace Tests\Unit;

use App\Support\PublicAssetUrl;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicAssetUrlTest extends TestCase
{
    public function test_local_and_relative_storage_urls_use_the_current_app_origin(): void
    {
        URL::forceRootUrl('https://admin.example.test');
        URL::forceScheme('https');

        $this->assertSame(
            'https://admin.example.test/storage/media/logo.webp',
            PublicAssetUrl::normalize('http://localhost:3000/storage/media/logo.webp'),
        );
        $this->assertSame(
            'https://admin.example.test/storage/media/logo.webp',
            PublicAssetUrl::normalize('/storage/media/logo.webp'),
        );
        $this->assertSame(
            'https://admin.example.test/storage/media/logo.webp',
            PublicAssetUrl::normalize('media/logo.webp'),
        );
    }

    public function test_external_asset_urls_are_preserved(): void
    {
        $url = 'https://cdn.example.test/assets/logo.webp?v=2';

        $this->assertSame($url, PublicAssetUrl::normalize($url));
    }
}
