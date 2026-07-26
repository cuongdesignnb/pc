<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class KiotImageMirrorService
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly KiotImageUrlGuard $guard,
        private readonly KiotConfigurationResolver $resolver,
    ) {}

    public function sync(Product $product, array $remoteImages, bool $dryRun, array &$report): void
    {
        $remoteImages = collect($remoteImages)
            ->filter(fn ($image) => is_array($image) && isset($image['id']))
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();
        $remoteIds = $remoteImages->pluck('id')->map(fn ($id) => (int) $id)->all();
        $existing = $product->images()->where('provider', 'kiot')->get()->keyBy('remote_image_id');

        foreach ($remoteImages as $remote) {
            $remoteId = (int) $remote['id'];
            $checksum = strtolower(trim((string) ($remote['checksum'] ?? '')));
            $current = $existing->get($remoteId);
            if (! preg_match('/^[a-f0-9]{64}$/', $checksum)) {
                $this->warning($report, 'IMAGE_DOWNLOAD_FAILED', $product, $remoteId, 'Missing or invalid checksum.');

                continue;
            }

            if ($current && hash_equals((string) $current->checksum, $checksum)) {
                $report['image_skips']++;
                if (! $dryRun) {
                    $current->fill([
                        'source_url' => (string) ($remote['url'] ?? $current->source_url),
                        'sort_order' => (int) ($remote['sort_order'] ?? 0),
                        'is_primary' => (bool) ($remote['is_primary'] ?? false),
                    ]);
                    if ($current->isDirty()) {
                        $current->synced_at = now();
                        $current->save();
                    }
                }

                continue;
            }

            $report['image_downloads']++;
            if ($dryRun) {
                continue;
            }

            try {
                $this->downloadAndStore($product, $remote, $checksum, $current);
                $report['images_downloaded']++;
            } catch (ConnectionException $exception) {
                $this->warning($report, 'IMAGE_TIMEOUT', $product, $remoteId, $exception->getMessage());
            } catch (KiotIntegrationException $exception) {
                $this->warning($report, $exception->errorCode, $product, $remoteId, $exception->getMessage());
            } catch (Throwable $exception) {
                $this->warning($report, 'IMAGE_DOWNLOAD_FAILED', $product, $remoteId, $exception->getMessage());
            }
        }

        $removed = $existing->reject(fn (ProductImage $image) => in_array((int) $image->remote_image_id, $remoteIds, true));
        $report['image_archives'] += $removed->count();
        if (! $dryRun) {
            foreach ($removed as $image) {
                $this->removeMirroredImage($image);
            }
        }
    }

    private function downloadAndStore(Product $product, array $remote, string $checksum, ?ProductImage $current): void
    {
        $runtime = $this->resolver->resolve();
        $url = $this->guard->assertSafe((string) ($remote['url'] ?? ''), (string) $runtime->baseUrl);
        $maxBytes = max(1024, (int) config('integrations.kiot.image_max_bytes', 8 * 1024 * 1024));
        $response = Http::connectTimeout(max(1, (int) config('integrations.kiot.image_connect_timeout_seconds', 3)))
            ->timeout(max(1, (int) config('integrations.kiot.image_timeout_seconds', 15)))
            ->withoutRedirecting()
            ->get($url);
        if (! $response->successful()) {
            throw new KiotIntegrationException('IMAGE_DOWNLOAD_FAILED', 'Provider image download failed.', 'retryable', $response->status());
        }

        $declaredLength = (int) ($response->header('Content-Length') ?: 0);
        $body = $response->body();
        if (($declaredLength > 0 && $declaredLength > $maxBytes) || strlen($body) > $maxBytes) {
            throw new KiotIntegrationException('IMAGE_TOO_LARGE', 'Provider image exceeds the configured size limit.', 'business_rejection');
        }
        if (! hash_equals($checksum, hash('sha256', $body))) {
            throw new KiotIntegrationException('IMAGE_DOWNLOAD_FAILED', 'Provider image checksum does not match.', 'business_rejection');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: '';
        $dimensions = @getimagesizefromstring($body);
        if (! isset(self::MIME_EXTENSIONS[$mime]) || $dimensions === false) {
            throw new KiotIntegrationException('IMAGE_MIME_INVALID', 'Provider image MIME is not allowed.', 'business_rejection');
        }

        $disk = (string) config('integrations.kiot.image_disk', 'public');
        $path = sprintf(
            'kiot/products/%d/%d-%s.%s',
            $product->id,
            (int) $remote['id'],
            $checksum,
            self::MIME_EXTENSIONS[$mime],
        );
        if (! Storage::disk($disk)->put($path, $body)) {
            throw new KiotIntegrationException('IMAGE_DOWNLOAD_FAILED', 'Website image storage rejected the file.', 'retryable');
        }

        $oldPath = $current?->storage_path;
        try {
            if ((bool) ($remote['is_primary'] ?? false)) {
                $product->images()->where('provider', 'kiot')->update(['is_primary' => false]);
            }
            ProductImage::updateOrCreate(
                ['provider' => 'kiot', 'remote_image_id' => (int) $remote['id']],
                [
                    'product_id' => $product->id,
                    'url' => $this->publicUrl($disk, $path),
                    'source_url' => $url,
                    'storage_path' => $path,
                    'checksum' => $checksum,
                    'mime_type' => $mime,
                    'file_size' => strlen($body),
                    'width' => (int) $dimensions[0],
                    'height' => (int) $dimensions[1],
                    'alt_text' => $product->name,
                    'sort_order' => (int) ($remote['sort_order'] ?? 0),
                    'is_primary' => (bool) ($remote['is_primary'] ?? false),
                    'synced_at' => now(),
                ],
            );
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $path && ! ProductImage::where('storage_path', $oldPath)->exists()) {
            Storage::disk($disk)->delete($oldPath);
        }
    }

    private function removeMirroredImage(ProductImage $image): void
    {
        $path = $image->storage_path;
        $image->delete();
        if ($path && ! ProductImage::where('storage_path', $path)->exists()) {
            Storage::disk((string) config('integrations.kiot.image_disk', 'public'))->delete($path);
        }
    }

    private function publicUrl(string $disk, string $path): string
    {
        $url = Storage::disk($disk)->url($path);

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://') ? $url : url($url);
    }

    private function warning(array &$report, string $code, Product $product, int $remoteImageId, string $message): void
    {
        $report['warning_details'][] = [
            'code' => $code,
            'remote_product_id' => $product->remote_product_id,
            'remote_image_id' => $remoteImageId,
            'message' => $message,
        ];
        $report['warnings']++;
        $report['errors']++;
    }
}
