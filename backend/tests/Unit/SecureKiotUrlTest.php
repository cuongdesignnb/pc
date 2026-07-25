<?php

namespace Tests\Unit;

use App\Exceptions\KiotIntegrationException;
use App\Services\Integrations\Kiot\SecureKiotUrl;
use Tests\TestCase;

class SecureKiotUrlTest extends TestCase
{
    public function test_it_normalizes_an_origin_and_rejects_unsafe_url_components(): void
    {
        $service = app(SecureKiotUrl::class);

        $this->assertSame('https://kiot.example.com:8443', $service->normalize('https://KIOT.example.com:8443/'));

        foreach ([
            '//kiot.example.com',
            'ftp://kiot.example.com',
            'https://user:pass@kiot.example.com',
            'https://kiot.example.com/api',
            'https://kiot.example.com?secret=value',
            "https://kiot.example.com\n.evil.test",
        ] as $url) {
            try {
                $service->normalize($url);
                $this->fail("Expected URL to be rejected: {$url}");
            } catch (KiotIntegrationException $exception) {
                $this->assertSame('INVALID_PROVIDER_URL', $exception->errorCode);
            }
        }
    }

    public function test_pairing_provider_origin_must_match_submitted_origin(): void
    {
        $this->expectException(KiotIntegrationException::class);
        $this->expectExceptionMessage('không khớp');

        app(SecureKiotUrl::class)->assertSameOrigin('https://kiot.example.com', 'https://other.example.com');
    }

    public function test_production_rejects_http_and_private_hosts(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $service = app(SecureKiotUrl::class);

        try {
            $service->normalize('http://kiot.example.com');
            $this->fail('Production HTTP URL must be rejected.');
        } catch (KiotIntegrationException $exception) {
            $this->assertSame('INSECURE_PROVIDER_URL', $exception->errorCode);
        }

        try {
            $service->normalize('https://127.0.0.1');
            $this->fail('Production loopback URL must be rejected.');
        } catch (KiotIntegrationException $exception) {
            $this->assertSame('PRIVATE_PROVIDER_URL', $exception->errorCode);
        }
    }
}
