<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Downloads an image from a public http(s) link so it can be stored like an
 * uploaded file. Private and internal addresses are refused, redirects are
 * not followed, and only real JPEG/PNG/GIF/WebP images up to 10 MB are kept.
 */
class RemoteImageDownloader
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    private const BLOCKED_HOSTS = ['localhost', 'localhost.localdomain', 'metadata.google.internal', 'metadata.azure.com'];

    /** @throws RuntimeException with a message that can be shown to staff */
    public function download(string $url): UploadedFile
    {
        $this->assertSafe($url);

        try {
            $response = Http::connectTimeout(5)->timeout(20)->withoutRedirecting()
                ->withHeaders(['User-Agent' => 'LaptopPlusMedia/1.0'])
                ->get($url);
        } catch (\Throwable) {
            throw new RuntimeException('Không tải được ảnh từ link này.');
        }
        if ($response->redirect()) {
            throw new RuntimeException('Link chuyển hướng sang địa chỉ khác. Hãy dùng link trực tiếp tới file ảnh.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Máy chủ ảnh trả về lỗi '.$response->status().'.');
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('Ảnh lớn hơn 10 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: '';
        if (! isset(self::MIME_EXTENSIONS[$mime]) || @getimagesizefromstring($body) === false) {
            throw new RuntimeException('Link không phải ảnh JPG, PNG, GIF hoặc WebP.');
        }

        $name = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
        $name = trim(urldecode($name)) !== '' ? trim(urldecode($name)) : 'anh-tu-link';
        $path = tempnam(sys_get_temp_dir(), 'media-url-');
        file_put_contents($path, $body);

        return new UploadedFile($path, $name.'.'.self::MIME_EXTENSIONS[$mime], $mime, null, true);
    }

    private function assertSafe(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! is_array($parts) || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! in_array($scheme, ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true))) {
            throw new RuntimeException('Link ảnh không hợp lệ.');
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $host = trim($host, '[]');
        if ($host === '' || in_array($host, self::BLOCKED_HOSTS, true)
            || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new RuntimeException('Không tải ảnh từ địa chỉ nội bộ.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolve($host);
        if ($addresses === []) {
            throw new RuntimeException('Không tìm thấy máy chủ của link ảnh.');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('Không tải ảnh từ địa chỉ nội bộ.');
            }
        }
    }

    /** @return list<string> */
    protected function resolve(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
