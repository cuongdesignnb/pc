<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;

class SecureKiotUrl
{
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'metadata.google.internal',
        'metadata.azure.com',
    ];

    public function normalize(string $url): string
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[^\x20-\x7E]/', $url) === 1 || str_starts_with($url, '//')) {
            throw $this->invalid();
        }

        $parts = parse_url($url);
        if (! is_array($parts) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw $this->invalid();
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $path = (string) ($parts['path'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! in_array($path, ['', '/'], true)) {
            throw $this->invalid();
        }

        if (app()->environment('production')) {
            if ($scheme !== 'https') {
                throw new KiotIntegrationException('INSECURE_PROVIDER_URL', 'KIOT URL phải sử dụng HTTPS trong production.');
            }

            $this->assertPublicHost($host);
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    public function assertSameOrigin(string $submittedUrl, string $providerUrl): void
    {
        if (! hash_equals($this->normalize($submittedUrl), $this->normalize($providerUrl))) {
            throw new KiotIntegrationException(
                'PAIRING_PROVIDER_ORIGIN_MISMATCH',
                'KIOT URL trả về không khớp với URL đã gửi.',
            );
        }
    }

    private function assertPublicHost(string $host): void
    {
        if (in_array($host, self::BLOCKED_HOSTS, true) || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw $this->invalid();
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->assertPublicIp($host);

            return;
        }

        $addresses = gethostbynamel($host) ?: [];
        $aaaaRecords = dns_get_record($host, DNS_AAAA) ?: [];
        foreach ($aaaaRecords as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        if ($addresses === []) {
            throw new KiotIntegrationException('PROVIDER_HOST_UNRESOLVED', 'Không thể xác minh KIOT URL.');
        }

        foreach (array_unique($addresses) as $address) {
            $this->assertPublicIp($address);
        }
    }

    private function assertPublicIp(string $address): void
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new KiotIntegrationException('PRIVATE_PROVIDER_URL', 'KIOT URL không được trỏ tới địa chỉ nội bộ.');
        }
    }

    private function invalid(): KiotIntegrationException
    {
        return new KiotIntegrationException('INVALID_PROVIDER_URL', 'KIOT URL không hợp lệ.');
    }
}
