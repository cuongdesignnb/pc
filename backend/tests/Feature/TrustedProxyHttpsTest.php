<?php

namespace Tests\Feature;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
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
                'HTTP_HOST' => 'admin.laptopplus.vn',
            ])
            ->withHeaders([
                'Host' => 'admin.laptopplus.vn',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => '',
            ])
            ->get('http://admin.laptopplus.vn/_test/forwarded-url');

        $response->assertOk();
        $response->assertJson([
            'secure' => true,
            'scheme' => 'https',
            'url' => 'https://admin.laptopplus.vn/build/test.css',
        ]);
    }

    public function test_admin_login_ignores_empty_forwarded_host_and_renders_secure_assets(): void
    {
        $buildDirectory = 'build-trusted-proxy-test';

        app(Vite::class)->useBuildDirectory($buildDirectory);
        File::ensureDirectoryExists(public_path($buildDirectory));
        File::put(public_path($buildDirectory.'/manifest.json'), json_encode([
            'resources/css/app.css' => [
                'file' => 'assets/app-test.css',
                'src' => 'resources/css/app.css',
                'isEntry' => true,
            ],
            'resources/js/app.js' => [
                'file' => 'assets/app-test.js',
                'src' => 'resources/js/app.js',
                'isEntry' => true,
                'css' => ['assets/app-test.css'],
            ],
        ], JSON_THROW_ON_ERROR));

        try {
            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' => '172.20.0.10',
                    'HTTP_HOST' => 'admin.laptopplus.vn',
                ])
                ->withHeaders([
                    'Host' => 'admin.laptopplus.vn',
                    'X-Forwarded-Proto' => 'https',
                    'X-Forwarded-Host' => '',
                ])
                ->get('http://admin.laptopplus.vn/admin/login');

            $response->assertOk();
            $response->assertSee('https://admin.laptopplus.vn/'.$buildDirectory.'/assets/app-test.js', false);
            $response->assertDontSee('http://admin.laptopplus.vn/', false);
        } finally {
            File::deleteDirectory(public_path($buildDirectory));
        }
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
