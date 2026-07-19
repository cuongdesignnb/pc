<?php

namespace Tests\Unit;

use App\Services\Integrations\Kiot\KiotSignatureService;
use Tests\TestCase;

class KiotSignatureServiceTest extends TestCase
{
    public function test_get_uses_empty_body_and_excludes_query_from_path(): void
    {
        $service = new KiotSignatureService;
        $canonical = $service->canonical('get', '/api/integrations/v1/pc/products?limit=1', 1721372400, 'nonce-1', '');

        $this->assertSame(implode("\n", [
            'GET', '/api/integrations/v1/pc/products', '1721372400', 'nonce-1', hash('sha256', ''),
        ]), $canonical);
    }

    public function test_post_signs_the_exact_unicode_json_body_and_preserves_decimal(): void
    {
        $service = new KiotSignatureService;
        $raw = json_encode(['name' => 'Nguyễn Văn A', 'amount' => 10.0], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $canonical = $service->canonical('POST', '/api/integrations/v1/pc/orders', 1721372400, 'nonce-2', $raw);

        $this->assertStringEndsWith(hash('sha256', $raw), $canonical);
        $this->assertStringContainsString('10.0', $raw);
        $this->assertSame(hash_hmac('sha256', $canonical, 'secret'), $service->sign('POST', '/api/integrations/v1/pc/orders', 1721372400, 'nonce-2', $raw, 'secret'));
    }

    public function test_each_header_set_has_a_fresh_nonce(): void
    {
        config()->set('integrations.kiot.client_id', 'pc-website');
        config()->set('integrations.kiot.secret', 'secret');
        $service = new KiotSignatureService;

        $first = $service->headers('GET', '/products', '');
        $second = $service->headers('GET', '/products', '');

        $this->assertNotSame($first['X-Nonce'], $second['X-Nonce']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first['X-Signature']);
    }
}
