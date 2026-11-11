<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS for the Zalo Mini App: read-only product list and detail, without
 * cookies or credentials. Other API routes keep the storefront-only CORS
 * policy in config/cors.php.
 */
class AllowZaloMiniAppProductReads
{
    private const ORIGIN_PATTERNS = [
        '#^https://h5\.zdn\.vn$#',
        '#^https://([a-z0-9-]+\.)+zalo\.me$#',
        '#^https://([a-z0-9-]+\.)+zdn\.vn$#',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->headers->get('Origin', '');
        if (! $this->applies($request, $origin)) {
            return $next($request);
        }

        if ($request->isMethod('OPTIONS')) {
            return $this->withHeaders(response('', 204), $origin, true);
        }

        return $this->withHeaders($next($request), $origin, false);
    }

    public static function allowsOrigin(string $origin): bool
    {
        foreach (self::ORIGIN_PATTERNS as $pattern) {
            if (preg_match($pattern, strtolower($origin)) === 1) {
                return true;
            }
        }

        return false;
    }

    private function applies(Request $request, string $origin): bool
    {
        if ($origin === '' || ! self::allowsOrigin($origin)) {
            return false;
        }
        if (! in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }
        $path = trim($request->path(), '/');

        // GET /api/v1/products, /api/v1/products/{slug} and the warranty lookup only.
        return $path === 'api/v1/products' || $path === 'api/v1/warranty-lookup'
            || (preg_match('#^api/v1/products/[^/]+$#', $path) === 1 && $path !== 'api/v1/products/featured');
    }

    private function withHeaders(Response $response, string $origin, bool $preflight): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->remove('Access-Control-Allow-Credentials');
        $response->headers->set('Vary', trim(implode(', ', array_filter([$response->headers->get('Vary'), 'Origin'])), ', '));
        if ($preflight) {
            $response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type, Accept-Language');
            $response->headers->set('Access-Control-Max-Age', '600');
        }

        return $response;
    }
}
