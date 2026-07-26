<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;

class KiotImageUrlGuard
{
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'metadata.google.internal',
        'metadata.azure.com',
        '169.254.169.254',
    ];

    public function assertSafe(string $url, string $providerBaseUrl): string
    {
        $parts = parse_url($url);
        $provider = parse_url($providerBaseUrl);
        if (! is_array($parts)
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])) {
            throw new KiotIntegrationException('IMAGE_DOWNLOAD_FAILED', 'Provider image URL is invalid.', 'business_rejection');
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $providerHost = strtolower(rtrim((string) ($provider['host'] ?? ''), '.'));
        $allowedHosts = array_map(
            fn (string $allowed): string => strtolower(rtrim($allowed, '.')),
            (array) config('integrations.kiot.image_allowed_hosts', []),
        );
        if ($host === '' || ($host !== $providerHost && ! in_array($host, $allowedHosts, true))) {
            throw new KiotIntegrationException('IMAGE_DOWNLOAD_FAILED', 'Provider image host is not allowed.', 'business_rejection');
        }
        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            throw new KiotIntegrationException('IMAGE_DOWNLOAD_FAILED', 'Provider image port is not allowed.', 'business_rejection');
        }

        $this->assertNotPrivate($host);

        return $url;
    }

    private function assertNotPrivate(string $host): void
    {
        if (in_array($host, self::BLOCKED_HOSTS, true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')) {
            throw new KiotIntegrationException('IMAGE_DOWNLOAD_FAILED', 'Provider image host is private.', 'business_rejection');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->assertPublicIp($host);

            return;
        }

        if (! app()->environment('production')) {
            return;
        }

        $addresses = gethostbynamel($host) ?: [];
        foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }
        if ($addresses === []) {
            throw new KiotIntegrationException('IMAGE_DOWNLOAD_FAILED', 'Provider image host cannot be resolved.', 'retryable');
        }
        foreach (array_unique($addresses) as $address) {
            $this->assertPublicIp($address);
        }
    }

    private function assertPublicIp(string $address): void
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new KiotIntegrationException('IMAGE_DOWNLOAD_FAILED', 'Provider image resolved to a private address.', 'business_rejection');
        }
    }
}
