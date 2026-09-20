<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProductContentResearchService
{
    public function __construct(private readonly AiConfigurationResolver $configuration) {}

    /** @return array{requested:bool,verified:bool,model_match:bool,specifications:array<int,array{label:string,value:string,unit:?string}>,sources:array<int,array<string,string>>,warnings:array<int,string>} */
    public function research(Product $product): array
    {
        $empty = ['requested' => true, 'verified' => false, 'model_match' => false, 'specifications' => [], 'sources' => [], 'warnings' => []];
        if (! (bool) Setting::get('ai_product_research_enabled', false)) {
            $empty['warnings'][] = 'Tra cứu web đang tắt trong Cài đặt AI.';

            return $empty;
        }

        $domains = $this->domainsForBrand($product->brand?->name);
        if ($domains === []) {
            $empty['warnings'][] = 'Chưa có domain chính hãng được whitelist cho thương hiệu này.';

            return $empty;
        }

        $config = $this->configuration->content();
        $baseUrl = rtrim((string) config('ai.research.base_url', 'https://api.openai.com/v1'), '/');
        $apiKey = trim((string) ($config['api_key'] ?? config('ai.research.api_key')));
        if ($apiKey === '' || parse_url($baseUrl, PHP_URL_HOST) !== 'api.openai.com') {
            $empty['warnings'][] = 'Web research cần OPENAI_API_KEY và Responses API chính thức của OpenAI.';

            return $empty;
        }

        $response = Http::timeout((int) config('ai.research.timeout', 120))
            ->withToken($apiKey)
            ->acceptJson()
            ->post($baseUrl.'/responses', [
                'model' => config('ai.research.model', $config['model'] ?? 'gpt-5.5'),
                'tools' => [[
                    'type' => 'web_search',
                    'filters' => ['allowed_domains' => $domains],
                ]],
                'tool_choice' => 'required',
                'instructions' => 'Bạn là bộ phận kiểm chứng thông số sản phẩm. Chỉ chấp nhận thông tin từ nguồn chính hãng trong domain được phép. Chỉ đánh dấu model_match=true khi trang nguồn nói đúng model hoặc SKU được cung cấp. Không suy đoán, không dùng nguồn bán lẻ hoặc diễn đàn. Trả JSON thuần.',
                'input' => "Tra cứu tài liệu thông số chính hãng cho sản phẩm: {$product->name}; SKU: {$product->sku}; thương hiệu: ".($product->brand?->name ?: 'không rõ').'. Trả đúng JSON: {"model_match":true|false,"specifications":[{"label":"","value":"","unit":null}],"summary":""}',
                'max_output_tokens' => 2500,
                'store' => false,
                'include' => ['web_search_call.action.sources'],
                'text' => ['format' => ['type' => 'json_object']],
            ]);

        if ($response->failed()) {
            throw new AiProviderException('Web research trả lỗi HTTP '.$response->status().'.', $response->status());
        }

        $payload = $response->json();
        $text = $this->outputText($payload);
        $data = json_decode($text, true);
        $sources = $this->sources($payload, $domains);
        $modelMatch = is_array($data) && filter_var($data['model_match'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $specifications = $modelMatch ? $this->specifications($data['specifications'] ?? []) : [];
        $verified = $modelMatch && $sources !== [] && $specifications !== [];

        return [
            'requested' => true,
            'verified' => $verified,
            'model_match' => $modelMatch,
            'specifications' => $specifications,
            'sources' => $sources,
            'warnings' => $verified ? [] : ['Không tìm thấy thông số chính hãng khớp chính xác model/SKU.'],
        ];
    }

    /** @return array<int,string> */
    private function domainsForBrand(?string $brand): array
    {
        $brand = Str::lower(Str::ascii(trim((string) $brand)));
        if ($brand === '') {
            return [];
        }

        $domains = [];
        foreach (preg_split('/\R/', (string) Setting::get('ai_product_research_official_domains', '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$mappedBrand, $mappedDomains] = array_pad(preg_split('/\s*[=:]\s*/', $line, 2), 2, null);
            if ($mappedDomains === null || Str::lower(Str::ascii(trim((string) $mappedBrand))) !== $brand) {
                continue;
            }
            foreach (preg_split('/\s*,\s*/', $mappedDomains) ?: [] as $domain) {
                $domain = strtolower(trim((string) $domain));
                $domain = preg_replace('#^https?://#', '', $domain);
                $domain = trim((string) $domain, '/');
                if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
                    $domains[] = $domain;
                }
            }
        }

        return array_values(array_unique($domains));
    }

    private function outputText(array $payload): string
    {
        if (filled($payload['output_text'] ?? null)) {
            return trim((string) $payload['output_text']);
        }
        foreach ((array) ($payload['output'] ?? []) as $item) {
            foreach ((array) ($item['content'] ?? []) as $content) {
                if (in_array($content['type'] ?? null, ['output_text', 'text'], true) && filled($content['text'] ?? null)) {
                    return trim((string) $content['text']);
                }
            }
        }

        return '';
    }

    /** @return array<int,array<string,string>> */
    private function sources(array $payload, array $domains): array
    {
        $sources = [];
        foreach ((array) ($payload['output'] ?? []) as $item) {
            if (($item['type'] ?? null) === 'web_search_call') {
                foreach ((array) data_get($item, 'action.sources', []) as $source) {
                    $sources[] = $source;
                }
            }
            foreach ((array) ($item['content'] ?? []) as $content) {
                foreach ((array) ($content['annotations'] ?? []) as $annotation) {
                    if (($annotation['type'] ?? null) === 'url_citation') {
                        $sources[] = $annotation;
                    }
                }
            }
        }

        return collect($sources)->map(function ($source) use ($domains) {
            $url = trim((string) ($source['url'] ?? ''));
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $allowed = collect($domains)->contains(fn ($domain) => $host === $domain || str_ends_with($host, '.'.$domain));
            if (! $allowed || ! filter_var($url, FILTER_VALIDATE_URL)) {
                return null;
            }

            return ['url' => $url, 'title' => trim((string) ($source['title'] ?? $host)), 'domain' => $host];
        })->filter()->unique('url')->values()->all();
    }

    /** @return array<int,array{label:string,value:string,unit:?string}> */
    private function specifications(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])->map(function ($item) {
            if (! is_array($item)) {
                return null;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $value = trim((string) ($item['value'] ?? ''));
            $unit = filled($item['unit'] ?? null) ? trim((string) $item['unit']) : null;

            return $label !== '' && $value !== '' ? compact('label', 'value', 'unit') : null;
        })->filter()->unique(fn (array $item) => Str::lower($item['label']))->take(40)->values()->all();
    }
}
