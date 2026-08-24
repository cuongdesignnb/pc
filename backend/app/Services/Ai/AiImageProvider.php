<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use App\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiImageProvider
{
    public function __construct(private readonly AiConfigurationResolver $configuration) {}

    public function generate(string $prompt, string $alt, ?int $userId = null): array
    {
        $config = $this->configuration->image();
        if (trim((string) $config['api_key']) === '') {
            throw new AiProviderException('Chưa cấu hình OPENAI_IMAGE_API_KEY.', 503);
        }
        if (filter_var($config['base_url'], FILTER_VALIDATE_URL) === false || parse_url($config['base_url'], PHP_URL_SCHEME) !== 'https') {
            throw new AiProviderException('Base URL AI hình ảnh phải là HTTPS hợp lệ.', 503);
        }

        $response = Http::timeout((int) $config['timeout'])
            ->withToken($config['api_key'])
            ->acceptJson()
            ->post($config['base_url'].'/images/generations', [
                'model' => $config['model'],
                'prompt' => $prompt.' Không có chữ, logo hoặc watermark.',
                'n' => 1,
                'size' => '1536x1024',
                'quality' => $config['quality'],
                'output_format' => 'png',
                'response_format' => 'b64_json',
            ]);

        if ($response->failed()) {
            throw new AiProviderException('Image provider trả lỗi HTTP '.$response->status().'.', $response->status());
        }

        $item = (array) data_get($response->json(), 'data.0', []);
        $bytes = ! empty($item['b64_json'])
            ? base64_decode($item['b64_json'], true)
            : $this->download((string) ($item['url'] ?? ''));
        if ($bytes === false || $bytes === '') {
            throw new AiProviderException('Image provider không trả về ảnh hợp lệ.', 502);
        }
        if (strlen($bytes) > (int) $config['max_bytes']) {
            throw new AiProviderException('Ảnh AI vượt quá giới hạn dung lượng.', 422);
        }

        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? 'image/png';
        if (! str_starts_with($mime, 'image/')) {
            throw new AiProviderException('Ảnh AI có MIME không hợp lệ.', 422);
        }

        $path = 'media/ai-generated/'.now()->format('Y-m').'/'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $bytes);
        $media = Media::create([
            'name' => Str::limit($alt, 120, ''),
            'file_name' => basename($path),
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $mime,
            'size' => strlen($bytes),
            'width' => $info[0] ?? null,
            'height' => $info[1] ?? null,
            'alt' => Str::limit($alt, 255, ''),
            'caption' => Str::limit('Ảnh minh họa: '.$alt, 1000, ''),
            'folder' => 'ai-generated',
            'uploaded_by' => $userId,
        ]);

        return [
            'url' => $media->url,
            'alt' => $media->alt,
            'caption' => $media->caption,
            'media_id' => $media->id,
        ];
    }

    private function download(string $url): string|false
    {
        if ($url === '' || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }
        $response = Http::timeout(30)->get($url);

        return $response->successful() ? $response->body() : false;
    }
}
