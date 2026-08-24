<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

class AiGenerationService
{
    public function __construct(
        private readonly AiContentProvider $content,
        private readonly AiImageProvider $images,
    ) {}

    public function generate(array $input, ?int $userId = null): array
    {
        $product = ! empty($input['product_id']) ? Product::find($input['product_id']) : null;
        $category = ! empty($input['category_id']) ? Category::find($input['category_id']) : null;
        if ($product) {
            $facts = "Tên: {$product->name}\nSKU: {$product->sku}\nMô tả hiện có: ".($product->description ?: 'trống')."\nThông số: ".($product->specifications_text ?: 'không có');
            $input['existing_content'] = trim(($input['existing_content'] ?? '')."\n".$facts);
            $input['topic'] = $input['topic'] ?: $product->name;
        } elseif ($category) {
            $input['existing_content'] = trim(($input['existing_content'] ?? '')."\nTên danh mục: {$category->name}\nMô tả hiện có: ".($category->description ?: 'trống'));
            $input['topic'] = $input['topic'] ?: $category->name;
        }

        $result = $this->content->generate($input, $input['topic']);
        $result['warnings'] = [];
        $result['images'] = [];
        $result['thumbnail'] = null;
        $result['thumbnail_alt'] = null;
        $result['thumbnail_caption'] = null;
        $result['og_image'] = null;

        if (! empty($input['with_images'])) {
            $alt = Str::limit($result['title'] ?: $input['topic'], 180, '');
            try {
                $thumbnail = $this->images->generate("Ảnh minh họa cho {$alt}", $alt, $userId);
                $result['thumbnail'] = $thumbnail['url'];
                $result['thumbnail_alt'] = $thumbnail['alt'];
                $result['thumbnail_caption'] = $thumbnail['caption'];
                $result['og_image'] = $thumbnail['url'];
            } catch (\Throwable $e) {
                $result['warnings'][] = 'Không tạo được ảnh đại diện: '.$e->getMessage();
            }

            $headings = $this->headings($result['content']);
            foreach (array_slice($headings, 0, (int) ($input['image_count'] ?? 0)) as $position => $heading) {
                try {
                    $image = $this->images->generate("Ảnh minh họa cho mục {$heading['text']}", $heading['text'], $userId);
                    $result['content'] = $this->insertFigure($result['content'], $heading['tag'], $heading['text'], $image);
                    $result['images'][] = [
                        'type' => 'inline', 'heading_tag' => $heading['tag'], 'heading' => $heading['text'],
                        'url' => $image['url'], 'alt' => $image['alt'], 'caption' => $image['caption'], 'position' => $position + 1,
                    ];
                } catch (\Throwable $e) {
                    $result['warnings'][] = 'Không tạo được ảnh cho mục '.Str::limit($heading['text'], 80, '').': '.$e->getMessage();
                }
            }
        }

        $result['featured_image'] = $result['thumbnail'];

        return $result;
    }

    private function headings(string $html): array
    {
        preg_match_all('/<(h2|h3)\b[^>]*>(.*?)<\/\1>/is', $html, $matches, PREG_SET_ORDER);

        return array_map(fn ($match) => ['tag' => $match[1], 'text' => trim(strip_tags($match[2]))], $matches);
    }

    private function insertFigure(string $html, string $tag, string $heading, array $image): string
    {
        $figure = '<figure class="ai-article-image"><img src="'.e($image['url']).'" alt="'.e($image['alt']).'" loading="lazy"><figcaption>'.e($image['caption']).'</figcaption></figure>';
        $pattern = '/(<'.preg_quote($tag, '/').'\b[^>]*>'.preg_quote($heading, '/').'<\/'.preg_quote($tag, '/').'>)/iu';

        return preg_replace($pattern, '$1'.$figure, $html, 1) ?: $html;
    }
}
