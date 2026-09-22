<?php

namespace App\Services\Ai;

use App\Http\Resources\ProductImageResource;
use App\Jobs\Ai\ProcessAiProductContentCampaignItem;
use App\Models\AiProductContentCampaign;
use App\Models\AiProductContentCampaignItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductContentCampaignService
{
    public function __construct(
        private readonly AiGenerationService $generation,
        private readonly ProductContentResearchService $research,
        private readonly ProductContentHeadingResolver $headings,
        private readonly ProductContactFooterService $footer,
    ) {}

    public function productsQuery(array $filters): Builder
    {
        $query = Product::query()->with(['category.parent', 'brand', 'specifications.specificationKey']);

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }
        if (filled($filters['category_id'] ?? null)) {
            $query->where('category_id', (int) $filters['category_id']);
        }
        if (filled($filters['brand_id'] ?? null)) {
            $query->where('brand_id', (int) $filters['brand_id']);
        }
        if (($filters['status'] ?? 'all') === 'active') {
            $query->where('is_active', true)->where('show_on_pc_website', true);
        } elseif (($filters['status'] ?? 'all') === 'hidden') {
            $query->where(fn (Builder $q) => $q->where('is_active', false)->orWhere('show_on_pc_website', false));
        }
        if (($filters['missing_description'] ?? false) === true) {
            $query->where(fn (Builder $q) => $q->whereNull('description')->orWhere('description', ''));
        }
        if (($filters['missing_specifications'] ?? false) === true) {
            $query->where(fn (Builder $q) => $q->whereNull('specifications_text')->orWhere('specifications_text', ''))
                ->whereDoesntHave('specifications');
        }

        return $query->orderBy('name');
    }

    public function snapshot(Product $product, bool $includeProductImages = false): array
    {
        $structured = $product->specifications->map(fn ($spec) => [
            'key' => $spec->specificationKey?->key,
            'label' => $spec->specificationKey?->label ?? $spec->specificationKey?->key,
            'value' => $spec->value,
            'unit' => $spec->specificationKey?->unit,
        ])->values()->all();

        $snapshot = [
            'description' => $product->description,
            'short_description' => $product->short_description,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'specifications_text' => $product->specifications_text,
            'structured_specifications' => $structured,
        ];

        if ($includeProductImages) {
            $snapshot['article_images'] = $this->articleImages($product);
        }

        return $snapshot;
    }

    /**
     * Return only product images that are already safe for the public
     * storefront. Provider/source URLs and unmirrored KIOT images are
     * deliberately excluded by ProductImageResource::usable().
     *
     * @return array<int,array{id:int,url:string,alt:string,title:string,caption:string,position:int}>
     */
    public function articleImages(Product $product): array
    {
        $productName = trim((string) $product->name) ?: 'Sản phẩm';

        return ProductImageResource::usable($product->images)
            ->take(4)
            ->values()
            ->map(function ($image, int $index) use ($productName): array {
                $alt = trim((string) $image->alt);
                if ($alt === '') {
                    $alt = $productName.' - hình ảnh sản phẩm '.($index + 1);
                }

                return [
                    'id' => (int) $image->id,
                    'url' => trim((string) $image->url),
                    'alt' => Str::limit($alt, 180, ''),
                    'title' => $productName,
                    'caption' => 'Hình ảnh sản phẩm: '.$productName,
                    'position' => $index + 1,
                ];
            })
            ->all();
    }

    public function facts(Product $product): string
    {
        $structured = $product->specifications->map(fn ($spec) => trim(($spec->specificationKey?->label ?? $spec->specificationKey?->key ?? '').': '.$spec->value.' '.($spec->specificationKey?->unit ?? '')))->filter()->implode("\n");
        $parsed = $product->parsed_specifications;
        $text = collect($parsed)->map(fn (array $spec) => trim($spec['label'].': '.$spec['value']))->filter()->implode("\n");

        return trim(implode("\n", array_filter([
            "Tên sản phẩm: {$product->name}",
            "SKU: {$product->sku}",
            'Thương hiệu: '.($product->brand?->name ?: 'không có'),
            'Mô tả hiện có: '.($product->description ?: 'không có'),
            "Thông số structured:\n".($structured ?: 'không có'),
            "Thông số văn bản:\n".($text ?: 'không có'),
        ])));
    }

    public function generateItem(AiProductContentCampaignItem $item): array
    {
        $campaign = $item->campaign;
        $product = $item->product()->with(['category.parent', 'brand', 'images', 'specifications.specificationKey'])->firstOrFail();
        $existingSpecs = $this->snapshot($product)['structured_specifications'];
        $articleImages = $campaign->include_product_images ? $this->articleImages($product) : [];
        $research = [
            'requested' => false,
            'verified' => false,
            'model_match' => false,
            'specifications' => [],
            'sources' => [],
            'warnings' => [],
        ];
        if ($campaign->use_web_research && $existingSpecs === [] && $product->parsed_specifications === []) {
            $research = $this->research->research($product);
        }

        $researchFacts = collect($research['specifications'] ?? [])->map(fn (array $spec) => trim($spec['label'].': '.$spec['value'].' '.($spec['unit'] ?? '')))->filter()->implode("\n");
        $existingFacts = $this->facts($product);
        if ($researchFacts !== '') {
            $existingFacts .= "\nThông số mới được kiểm chứng từ nguồn chính hãng:\n{$researchFacts}";
        }
        $result = $this->generation->generate([
            'topic' => $product->name,
            'keywords' => $product->brand?->name,
            'type' => 'product_description',
            'tone' => 'professional',
            'length' => 'medium',
            'full_article' => true,
            'with_images' => false,
            'image_count' => 0,
            'product_id' => $product->id,
            'existing_content' => trim($existingFacts),
        ], $campaign->created_by);

        $hasVerifiedFacts = $existingSpecs !== [] || $product->parsed_specifications !== [] || ($research['verified'] && $research['specifications'] !== []);
        $warnings = array_values(array_unique(array_merge($result['warnings'] ?? [], $research['warnings'] ?? [])));
        if ($campaign->include_product_images && $articleImages === []) {
            $warnings[] = 'Sản phẩm chưa có ảnh public hợp lệ để chèn vào nội dung.';
        }

        $payload = [
            'content' => $result['content'],
            'short_description' => Str::limit(strip_tags($result['short_description'] ?: $result['excerpt']), 500, ''),
            'meta_title' => $result['meta_title'],
            'meta_description' => $result['meta_description'],
            'content_heading' => $this->headings->contentHeading($hasVerifiedFacts),
            'technical_heading' => $this->headings->resolve($product, $campaign->technical_heading),
            'proposed_specifications' => $research['verified'] ? $research['specifications'] : [],
            'include_product_images' => (bool) $campaign->include_product_images,
            'article_images' => $articleImages,
            'research' => [
                'requested' => (bool) ($research['requested'] ?? false),
                'verified' => (bool) ($research['verified'] ?? false),
                'model_match' => (bool) ($research['model_match'] ?? false),
                'source_count' => count($research['sources'] ?? []),
            ],
            'can_auto_apply' => ! $research['requested'] || $research['verified'],
            'warnings' => array_values(array_unique($warnings)),
        ];

        $item->update([
            'generated_payload' => $payload,
            'research_sources' => $research['sources'] ?? [],
            'status' => $campaign->mode === 'publish' && ! $payload['can_auto_apply'] ? 'needs_review' : 'draft',
            'generated_at' => now(),
            'error_message' => null,
            'warnings' => $payload['warnings'],
            'locked_at' => null,
        ]);

        return $payload;
    }

    public function applyItem(AiProductContentCampaignItem $item): void
    {
        $snapshotConflict = false;
        DB::transaction(function () use ($item, &$snapshotConflict) {
            $item = AiProductContentCampaignItem::query()->lockForUpdate()->with('campaign')->findOrFail($item->id);
            $product = Product::query()->lockForUpdate()->with(['images', 'specifications.specificationKey'])->findOrFail($item->product_id);
            $payload = $item->generated_payload;
            if (! is_array($payload) || ! in_array($item->status, ['draft', 'needs_review'], true)) {
                throw new \RuntimeException('Item chưa có bản nháp để áp dụng.');
            }
            $currentSnapshot = $this->snapshot($product, (bool) $item->campaign->include_product_images);
            $sourceSnapshot = $item->source_snapshot;
            // Campaigns created before article image snapshots existed remain
            // applicable; new campaigns always carry this key and therefore
            // detect image/ALT changes before applying.
            if (! array_key_exists('article_images', $sourceSnapshot)) {
                unset($currentSnapshot['article_images']);
            }
            if ($this->canonicalSnapshot($currentSnapshot) !== $this->canonicalSnapshot($sourceSnapshot)) {
                $snapshotConflict = true;

                return;
            }

            $product->update([
                'description' => $payload['content'],
                'short_description' => $payload['short_description'],
                'meta_title' => $payload['meta_title'],
                'meta_description' => $payload['meta_description'],
            ]);

            $proposed = is_array($payload['proposed_specifications'] ?? null) ? $payload['proposed_specifications'] : [];
            if ($proposed !== [] && ! $product->specifications()->exists() && blank($product->specifications_text)) {
                $product->update(['specifications_text' => collect($proposed)->map(fn (array $spec) => $spec['label'].': '.$spec['value'].(filled($spec['unit'] ?? null) ? ' '.$spec['unit'] : ''))->implode("\n")]);
            }
            if ($item->campaign->append_contact_footer) {
                $this->footer->ensure($product);
            }
            if ($item->campaign->include_product_images && array_key_exists('article_images', $payload)) {
                // Recompute from the locked product instead of trusting the
                // stored preview payload, so an admin cannot apply an unsafe
                // or unrelated URL by modifying campaign JSON.
                $this->syncArticleImageBlocks($product, $this->articleImages($product));
            }

            $item->update(['status' => 'applied', 'applied_at' => now(), 'error_message' => null]);
            $item->campaign->refreshProgress();
        });

        if ($snapshotConflict) {
            $message = 'Sản phẩm đã thay đổi sau khi tạo bản nháp. Hãy tạo lại nội dung.';
            $conflictItem = AiProductContentCampaignItem::query()->with('campaign')->findOrFail($item->id);
            $conflictItem->update([
                'status' => 'needs_review',
                'error_message' => 'Sản phẩm đã thay đổi sau khi tạo bản nháp.',
            ]);
            $conflictItem->campaign->refreshProgress();

            throw new \RuntimeException($message);
        }
    }

    /**
     * Replace only image blocks previously managed by this campaign feature.
     * Hand-authored image_text blocks remain untouched.
     *
     * @param  array<int,array{id:int,url:string,alt:string,title:string,caption:string,position:int}>  $images
     */
    private function syncArticleImageBlocks(Product $product, array $images): void
    {
        $product->detailBlocks()
            ->where('type', 'image_text')
            ->get()
            ->filter(fn ($block): bool => ($block->payload['managed_by'] ?? null) === 'ai-product-content-campaign')
            ->each(fn ($block) => $block->delete());

        foreach (array_values($images) as $index => $image) {
            $product->detailBlocks()->create([
                'type' => 'image_text',
                'title' => 'Hình ảnh sản phẩm',
                'payload' => [
                    'managed_by' => 'ai-product-content-campaign',
                    'source_image_id' => $image['id'],
                    'image_url' => $image['url'],
                    'alt' => $image['alt'],
                    'description' => $image['caption'],
                ],
                'sort_order' => 900 + $index,
                'is_active' => true,
            ]);
        }
    }

    public function dispatch(AiProductContentCampaign $campaign): int
    {
        $campaign->update(['status' => 'processing', 'started_at' => $campaign->started_at ?: now()]);
        $staleLock = now()->subMinutes(15);
        $items = $campaign->items()
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 3)
            ->where(fn (Builder $query) => $query->whereNull('locked_at')->orWhere('locked_at', '<', $staleLock))
            ->get(['id']);

        foreach ($items as $item) {
            $item->update(['locked_at' => now()]);
            ProcessAiProductContentCampaignItem::dispatch($item->id);
        }

        return $items->count();
    }

    private function canonicalSnapshot(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(fn (mixed $item): mixed => $this->canonicalSnapshot($item), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
