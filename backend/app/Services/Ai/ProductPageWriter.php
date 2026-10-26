<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Writes a full product page from the product title: long description,
 * technical specifications, highlights and the typed detail blocks the
 * storefront renders as cards. Output only fills the admin form; nothing is
 * saved until the administrator reviews it and presses Save.
 */
class ProductPageWriter
{
    public const SECTIONS = ['description', 'specifications', 'highlights', 'blocks'];

    private const WORDS = ['short' => '350-450', 'medium' => '700-900', 'long' => '1300-1600'];

    private const MIN_OUTPUT_TOKENS = 8000;

    public function __construct(
        private readonly AiContentProvider $content,
        private readonly AiContentSanitizer $sanitizer,
    ) {}

    /** @param list<string> $sections */
    public function generate(array $input, ?Product $product, array $sections = self::SECTIONS): array
    {
        $sections = array_values(array_intersect(self::SECTIONS, $sections ?: self::SECTIONS));
        $raw = $this->content->complete($this->systemPrompt(), $this->prompt($input, $product, $sections), self::MIN_OUTPUT_TOKENS);
        $data = json_decode(trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw))), true);
        if (! is_array($data)) {
            throw new AiProviderException('AI trả về dữ liệu không đúng định dạng JSON. Vui lòng thử lại hoặc chọn độ dài ngắn hơn.', 502);
        }

        $result = in_array('description', $sections, true)
            ? $this->sanitizer->parse(json_encode($data, JSON_UNESCAPED_UNICODE), 'product_description', $input['topic'])
            : $this->emptyText($input['topic']);

        // The product form accepts at most 500 characters here; a longer value
        // would make the whole save fail validation.
        $result['short_description'] = Str::limit((string) $result['short_description'], 500, '');
        $result['excerpt'] = Str::limit((string) $result['excerpt'], 500, '');
        $result['specifications'] = in_array('specifications', $sections, true) ? $this->specifications($data['specifications'] ?? []) : [];
        $result['specifications_text'] = collect($result['specifications'])
            ->map(fn (array $spec) => $spec['label'].': '.$spec['value'])
            ->implode("\n");
        $result['highlights'] = in_array('highlights', $sections, true) ? $this->highlights($data['highlights'] ?? []) : [];
        $result['detail_blocks'] = in_array('blocks', $sections, true) ? $this->blocks($data['detail_blocks'] ?? []) : [];
        $result['sections'] = $sections;

        return $result;
    }

    private function systemPrompt(): string
    {
        return 'Bạn là chuyên viên nội dung sản phẩm cho cửa hàng máy tính, laptop và linh kiện tại Việt Nam. '
            .'Viết tiếng Việt có dấu, tự nhiên, chuẩn SEO, không nhồi từ khóa. '
            .'Trả về đúng một đối tượng JSON thuần, không Markdown fence, không chú thích. '
            .'HTML trong "content" chỉ dùng h2, h3, p, ul, ol, li, strong, em; không dùng h1. '
            .'Thông số phải lấy từ tên sản phẩm và thông số công khai của chính xác model đó; '
            .'thông số nào không chắc chắn thì bỏ qua, tuyệt đối không bịa. '
            .'Không nêu giá, khuyến mãi, quà tặng, thời hạn bảo hành hay cam kết của cửa hàng.';
    }

    /** @param list<string> $sections */
    private function prompt(array $input, ?Product $product, array $sections): string
    {
        $words = self::WORDS[$input['length'] ?? 'medium'] ?? self::WORDS['medium'];
        $tone = ['professional' => 'chuyên nghiệp', 'casual' => 'thân thiện', 'luxury' => 'cao cấp'][$input['tone'] ?? ''] ?? 'chuyên nghiệp';

        $facts = ['Tên sản phẩm (nguồn chính): '.$input['topic']];
        if ($product) {
            $facts[] = 'SKU: '.$product->sku;
            if ($product->brand?->name) {
                $facts[] = 'Thương hiệu: '.$product->brand->name;
            }
            if ($product->category?->name) {
                $facts[] = 'Danh mục: '.$product->category->name;
            }
            if (filled($product->specifications_text)) {
                $facts[] = "Thông số đang có (ưu tiên giữ đúng):\n".$product->specifications_text;
            }
        }
        if (filled($input['keywords'] ?? null)) {
            $facts[] = 'Từ khóa SEO: '.$input['keywords'];
        }
        if (filled($input['existing_content'] ?? null)) {
            $facts[] = "Nội dung hiện có để tham khảo:\n".Str::limit(strip_tags((string) $input['existing_content']), 4000);
        }

        $schema = [];
        $rules = [];
        if (in_array('description', $sections, true)) {
            $schema[] = '"short_description": "2-3 câu tóm tắt điểm mạnh, tối đa 300 ký tự"';
            $schema[] = '"content": "HTML mô tả chi tiết"';
            $schema[] = '"meta_title": "tối đa 60 ký tự"';
            $schema[] = '"meta_desc": "tối đa 160 ký tự"';
            $schema[] = '"meta_keywords": "5-8 từ khóa, phân tách bằng dấu phẩy"';
            $rules[] = "- content: khoảng {$words} từ, 5-7 mục h2 (ví dụ: Tổng quan, Thiết kế, Màn hình, Hiệu năng, Kết nối và pin, Phù hợp với ai, Kết luận), mỗi mục 2-3 đoạn p, dùng ul/li khi liệt kê.";
        }
        if (in_array('specifications', $sections, true)) {
            $schema[] = '"specifications": [{"label": "CPU", "value": "Intel Core i5-6440HQ (4 nhân 4 luồng, 2.6-3.5 GHz)"}]';
            $rules[] = '- specifications: 10-25 dòng theo thứ tự CPU, RAM, Ổ cứng, Card đồ họa, Màn hình, Cổng kết nối, Kết nối không dây, Pin, Hệ điều hành, Kích thước, Trọng lượng, Màu sắc (tùy loại sản phẩm). Giữ đúng các giá trị có trong tên sản phẩm.';
        }
        if (in_array('highlights', $sections, true)) {
            $schema[] = '"highlights": ["4-6 điểm nổi bật, mỗi điểm tối đa 80 ký tự"]';
        }
        if (in_array('blocks', $sections, true)) {
            $schema[] = '"detail_blocks": ['
                .'{"type": "benchmark_cards", "title": "Cấu hình nổi bật", "cards": [{"title": "Bộ xử lý", "value": "Core i5-6440HQ", "description": "4 nhân, tối đa 3.5 GHz"}]}, '
                .'{"type": "feature_cards", "title": "Điểm mạnh", "cards": [{"title": "", "value": "", "description": ""}]}, '
                .'{"type": "use_case_cards", "title": "Phù hợp cho", "cards": [{"icon": "💼", "title": "Văn phòng", "description": ""}]}'
                .']';
            $rules[] = '- detail_blocks: đúng 3 khối như mẫu; benchmark_cards 3 thẻ lấy từ thông số thật (value ngắn, tối đa 24 ký tự); feature_cards 4-6 thẻ; use_case_cards 3 thẻ có icon emoji. Mỗi description tối đa 120 ký tự, văn bản thuần.';
        }

        return "Viết trang sản phẩm với giọng văn {$tone}.\n\n"
            .implode("\n", $facts)."\n\n"
            ."Yêu cầu:\n".implode("\n", $rules)."\n\n"
            .'Trả về JSON với đúng các khóa: {'.implode(', ', $schema).'}';
    }

    private function emptyText(string $title): array
    {
        return [
            'title' => $title, 'excerpt' => '', 'content' => '', 'body' => '', 'short_description' => '',
            'meta_title' => '', 'meta_desc' => '', 'meta_description' => '', 'meta_keywords' => '', 'tags' => [],
        ];
    }

    /** @return list<array{label:string,value:string}> */
    private function specifications(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(fn (mixed $item): ?array => is_array($item)
                ? ['label' => $this->text($item['label'] ?? $item['name'] ?? '', 60), 'value' => $this->text($item['value'] ?? '', 200)]
                : null)
            // "Label: Value" lines are parsed on the first colon, so a label must not contain one.
            ->filter(fn (?array $spec) => $spec && $spec['label'] !== '' && $spec['value'] !== '' && ! str_contains($spec['label'], ':'))
            ->unique('label')
            ->take(40)
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function highlights(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(fn (mixed $item) => $this->text(is_array($item) ? ($item['title'] ?? '') : $item, 120))
            ->filter()
            ->unique()
            ->take(6)
            ->values()
            ->all();
    }

    /** @return list<array{type:string,title:string,payload:array{cards:list<array<string,string>>}}> */
    private function blocks(mixed $items): array
    {
        $allowed = ['benchmark_cards', 'feature_cards', 'use_case_cards'];

        return collect(is_array($items) ? $items : [])
            ->filter(fn (mixed $block) => is_array($block) && in_array($block['type'] ?? null, $allowed, true))
            ->unique('type')
            ->map(function (array $block): array {
                $cards = collect(is_array($block['cards'] ?? null) ? $block['cards'] : ($block['payload']['cards'] ?? []))
                    ->filter(fn (mixed $card) => is_array($card))
                    ->map(fn (array $card) => array_filter([
                        'icon' => $block['type'] === 'use_case_cards' ? $this->text($card['icon'] ?? '', 8) : '',
                        'title' => $this->text($card['title'] ?? '', 80),
                        'value' => $this->text($card['value'] ?? '', 40),
                        'description' => $this->text($card['description'] ?? '', 160),
                    ], fn (string $value) => $value !== ''))
                    ->filter(fn (array $card) => isset($card['title']))
                    ->take(6)
                    ->values()
                    ->all();

                return ['type' => $block['type'], 'title' => $this->text($block['title'] ?? '', 120), 'payload' => ['cards' => $cards]];
            })
            ->filter(fn (array $block) => $block['payload']['cards'] !== [])
            ->values()
            ->all();
    }

    private function text(mixed $value, int $limit): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        $value = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return Str::limit($value, $limit, '');
    }
}
