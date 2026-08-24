<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Http;

class AiContentProvider
{
    public function __construct(
        private readonly AiConfigurationResolver $configuration,
        private readonly AiContentSanitizer $sanitizer,
    ) {}

    public function generate(array $input, ?string $fallbackTitle = null): array
    {
        $config = $this->configuration->content();
        $this->assertHttps($config['base_url']);
        if (trim((string) $config['api_key']) === '') {
            throw new AiProviderException('Chưa cấu hình OPENAI_API_KEY cho AI nội dung.', 503);
        }

        $type = $input['type'];
        $prompt = $this->prompt($input);
        $request = Http::timeout((int) $config['timeout'])
            ->withToken($config['api_key'])
            ->acceptJson();

        $response = $config['wire_api'] === 'responses'
            ? $request->post($config['base_url'].'/responses', [
                'model' => $config['model'],
                'instructions' => $this->systemPrompt($type),
                'input' => $prompt,
                'reasoning' => ['effort' => $config['reasoning_effort']],
                'max_output_tokens' => (int) $config['max_tokens'],
                'store' => false,
                'text' => ['format' => ['type' => 'json_object']],
            ])
            : $request->post($config['base_url'].'/chat/completions', [
                'model' => $config['model'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt($type)],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => (int) $config['max_tokens'],
                'temperature' => 0.7,
            ]);

        if ($response->failed()) {
            throw new AiProviderException('AI provider trả lỗi HTTP '.$response->status().'.', $response->status());
        }

        $text = $this->extractText($response->json());
        if ($text === '') {
            throw new AiProviderException('AI provider không trả về nội dung.', 502);
        }

        return $this->sanitizer->parse($text, $type, $fallbackTitle ?? $input['topic']);
    }

    private function systemPrompt(string $type): string
    {
        return 'Bạn là biên tập viên nội dung tiếng Việt cho website công nghệ. '
            .'Trả về JSON thuần, không Markdown fence. Dùng HTML an toàn với h2, h3, p, ul, ol, li, strong, em. '
            .'Không bịa thông số, giá, bảo hành hoặc cam kết không có trong dữ liệu. '
            .(in_array($type, ['product_description', 'category_description'], true)
                ? 'Không dùng h1 vì trang đã có tiêu đề chính.'
                : '');
    }

    private function prompt(array $input): string
    {
        $length = ['short' => 'ngắn', 'medium' => 'vừa', 'long' => 'dài'][$input['length']] ?? 'vừa';
        $tone = ['professional' => 'chuyên nghiệp', 'casual' => 'thân thiện', 'luxury' => 'cao cấp'][$input['tone']] ?? 'chuyên nghiệp';
        $schema = $input['type'] === 'category_description'
            ? '{"content":"HTML","meta_title":"","meta_desc":""}'
            : '{"title":"","excerpt":"","content":"HTML","meta_title":"","meta_desc":"","meta_keywords":"","tags":[]}';

        return "Chủ đề: {$input['topic']}\n"
            ."Loại nội dung: {$input['type']}\n"
            .'Từ khóa: '.($input['keywords'] ?: 'không có')."\n"
            ."Giọng văn: {$tone}; độ dài: {$length}.\n"
            ."Nội dung hiện có để tham khảo, không được bịa thêm dữ kiện:\n".($input['existing_content'] ?: 'không có')."\n"
            ."Hãy trả đúng JSON theo mẫu: {$schema}";
    }

    private function extractText(array $payload): string
    {
        foreach ([
            data_get($payload, 'output_text'),
            data_get($payload, 'response.output_text'),
            data_get($payload, 'data.output_text'),
        ] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        foreach ((array) data_get($payload, 'output', []) as $item) {
            foreach ((array) ($item['content'] ?? []) as $content) {
                if (in_array($content['type'] ?? null, ['output_text', 'text'], true) && ! empty($content['text'])) {
                    return trim((string) $content['text']);
                }
            }
        }

        return trim((string) (data_get($payload, 'choices.0.message.content')
            ?: data_get($payload, 'choices.0.text', '')));
    }

    private function assertHttps(string $url): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new AiProviderException('Base URL AI phải là HTTPS hợp lệ.', 503);
        }
    }
}
