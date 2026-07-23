<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyHttpsTest extends TestCase
{
    public function test_forwarded_https_request_from_private_proxy_generates_https_urls(): void
    {
        $this->registerForwardedUrlRoute();

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '172.20.0.10',
            ])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'admin.laptopplus.vn',
                'X-Forwarded-Port' => '443',
            ])
            ->get('/_test/forwarded-url');

        $response->assertOk();
        $response->assertJson([
            'secure' => true,
            'scheme' => 'https',
            'url' => 'https://admin.laptopplus.vn/build/test.css',
        ]);
    }

    public function test_forwarded_headers_from_public_client_are_not_trusted(): void
    {
        $this->registerForwardedUrlRoute();

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.10',
            ])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'admin.laptopplus.vn',
                'X-Forwarded-Port' => '443',
            ])
            ->get('/_test/forwarded-url');

        $response->assertOk();
        $response->assertJson([
            'secure' => false,
            'scheme' => 'http',
        ]);
    }

    private function registerForwardedUrlRoute(): void
    {
        Route::get('/_test/forwarded-url', function () {
            return response()->json([
                'secure' => request()->isSecure(),
                'scheme' => request()->getScheme(),
                'url' => url('/build/test.css'),
            ]);
        });
    }
}
