<?php

namespace Tests\Unit;

use App\Support\GoogleMapsEmbed;
use App\Support\YouTubeVideo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StorefrontEmbedUrlsTest extends TestCase
{
    public static function youtubeLinks(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30s', 'dQw4w9WgXcQ'],
            'short link' => ['https://youtu.be/dQw4w9WgXcQ?si=abc', 'dQw4w9WgXcQ'],
            'shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'embed' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'live' => ['https://www.youtube.com/live/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'mobile without scheme' => ['m.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'other host' => ['https://evil.example/watch?v=dQw4w9WgXcQ', null],
            'lookalike host' => ['https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ', null],
            'bad id' => ['https://www.youtube.com/watch?v=short', null],
            'channel' => ['https://www.youtube.com/@laptopplus', null],
            'javascript' => ['javascript:alert(1)', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('youtubeLinks')]
    public function test_youtube_ids_are_extracted_only_from_youtube(string $url, ?string $expected): void
    {
        $this->assertSame($expected, YouTubeVideo::id($url));
    }

    public function test_youtube_array_uses_privacy_enhanced_embed(): void
    {
        $this->assertSame([
            'id' => 'dQw4w9WgXcQ',
            'watch_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'embed_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        ], YouTubeVideo::toArray('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertNull(YouTubeVideo::toArray(null));
    }

    public function test_google_maps_iframe_and_url_are_reduced_to_embed_src(): void
    {
        $src = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3724!2d105.8!3d21.0';

        $this->assertSame($src, GoogleMapsEmbed::normalize(
            '<iframe src="'.$src.'" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>'
        ));
        $this->assertSame($src, GoogleMapsEmbed::normalize($src));
        $this->assertSame(
            'https://maps.google.com/maps?q=Laptop+Plus&output=embed',
            GoogleMapsEmbed::normalize('https://maps.google.com/maps?q=Laptop+Plus&output=embed'),
        );
    }

    public function test_google_maps_rejects_non_embed_and_foreign_urls(): void
    {
        $this->assertNull(GoogleMapsEmbed::normalize('https://maps.app.goo.gl/abc123'));
        $this->assertNull(GoogleMapsEmbed::normalize('https://www.google.com/maps/place/Laptop+Plus'));
        $this->assertNull(GoogleMapsEmbed::normalize('http://www.google.com/maps/embed?pb=1'));
        $this->assertNull(GoogleMapsEmbed::normalize('https://evil.example/maps/embed?pb=1'));
        $this->assertNull(GoogleMapsEmbed::normalize('<iframe src="https://evil.example/x"></iframe>'));
        $this->assertNull(GoogleMapsEmbed::normalize(''));
    }
}
