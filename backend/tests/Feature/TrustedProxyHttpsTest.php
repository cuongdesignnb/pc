<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tighten\Ziggy\Ziggy;

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

        config(['app.url' => 'https://admin.laptopplus.vn']);
        app(Kernel::class)->prependMiddleware(PrimeUrlGeneratorBeforeTrustedProxy::class);
        app(Kernel::class)->pushMiddleware(CaptureAdminUrlState::class);

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
            $this->assertSame(0, substr_count($response->getContent(), 'http://admin.laptopplus.vn/'.$buildDirectory.'/'));
            $this->assertSame(4, substr_count($response->getContent(), 'https://admin.laptopplus.vn/'.$buildDirectory.'/'));
            $this->assertSame([
                'config_app_url' => 'https://admin.laptopplus.vn',
                'request_root' => 'https://admin.laptopplus.vn',
                'request_scheme' => 'https',
                'request_http_host' => 'admin.laptopplus.vn',
                'url' => 'https://admin.laptopplus.vn',
                'asset' => 'https://admin.laptopplus.vn/build/test.css',
                'secure_asset' => 'https://admin.laptopplus.vn/build/test.css',
                'vite_asset' => 'https://admin.laptopplus.vn/'.$buildDirectory.'/assets/app-test.js',
                'ziggy_url' => 'https://admin.laptopplus.vn',
            ], CaptureAdminUrlState::$dump);
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

class PrimeUrlGeneratorBeforeTrustedProxy
{
    public function handle(Request $request, Closure $next): mixed
    {
        url('/');

        return $next($request);
    }
}

class CaptureAdminUrlState
{
    public static array $dump = [];

    public function handle(Request $request, Closure $next): mixed
    {
        self::$dump = [
            'config_app_url' => config('app.url'),
            'request_root' => $request->root(),
            'request_scheme' => $request->getScheme(),
            'request_http_host' => $request->getHttpHost(),
            'url' => url('/'),
            'asset' => asset('build/test.css'),
            'secure_asset' => secure_asset('build/test.css'),
            'vite_asset' => app(Vite::class)->asset('resources/js/app.js'),
            'ziggy_url' => (new Ziggy)->toArray()['url'],
        ];

        return $next($request);
    }
}
