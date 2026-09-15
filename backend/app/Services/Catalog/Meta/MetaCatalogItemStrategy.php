<?php

namespace App\Services\Catalog\Meta;

use App\Data\Catalog\CatalogProductData;
use App\Data\Catalog\CatalogValidationResult;
use App\Services\Catalog\Feeds\CatalogFeedItemStrategy;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MetaCatalogItemStrategy implements CatalogFeedItemStrategy
{
    public function __construct(private readonly MetaCatalogItemValidator $validator) {}

    public function items(CatalogProductData $product): array
    {
        $variants = array_values(array_filter(
            $product->variants,
            fn (array $variant): bool => (bool) ($variant['is_active'] ?? false),
        ));

        if ($variants === []) {
            return [$this->item($product)];
        }

        return array_map(
            fn (array $variant): MetaCatalogItem => $this->item($product, $variant),
            $variants,
        );
    }

    public function validate(object $item, CatalogProductData $source): CatalogValidationResult
    {
        return $this->validator->validate($this->metaItem($item), $source);
    }

    public function externalId(object $item): string
    {
        return $this->metaItem($item)->value('id');
    }

    public function checksum(CatalogProductData $source, array $items): string
    {
        return hash('sha256', json_encode([
            'source' => $source->checksum,
            'items' => array_map(
                fn (object $item): string => $this->metaItem($item)->checksum(),
                $items,
            ),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function duplicateIdErrorCode(): string
    {
        return 'DUPLICATE_ID';
    }

    public function summaryCounters(CatalogProductData $source, array $items): array
    {
        $metaItems = array_map(fn (object $item): MetaCatalogItem => $this->metaItem($item), $items);
        $hasVariants = count(array_filter($metaItems, fn (MetaCatalogItem $item): bool => $item->variant)) > 0;
        $hasItemGroup = count(array_filter(
            $metaItems,
            fn (MetaCatalogItem $item): bool => $item->value('item_group_id') !== '',
        )) > 0;
        $hasSellableInventory = count(array_filter(
            $metaItems,
            fn (MetaCatalogItem $item): bool => (int) $item->value('quantity_to_sell_on_facebook') > 0,
        )) > 0;

        return [
            'PRODUCTS_WITH_VARIANTS' => $hasVariants ? 1 : 0,
            'PRODUCTS_WITH_ITEM_GROUP_ID' => $hasItemGroup ? 1 : 0,
            'PRODUCTS_WITHOUT_SELLABLE_INVENTORY' => $hasSellableInventory ? 0 : 1,
        ];
    }

    private function item(CatalogProductData $product, ?array $variant = null): MetaCatalogItem
    {
        $variantId = (int) ($variant['id'] ?? 0);
        $variantName = trim((string) ($variant['name'] ?? ''));
        $variantSku = trim((string) ($variant['sku'] ?? ''));
        $isVariant = $variant !== null;
        $id = $product->externalId;
        if ($isVariant) {
            $id = $id === '' || $variantId <= 0 ? '' : $id.':variant:'.$variantId;
        }

        $attributes = $this->normalizeAttributes(array_merge(
            $product->attributes,
            (array) ($variant['attributes'] ?? []),
        ));
        $regularPrice = $isVariant
            ? max(0, (int) ($variant['price'] ?? 0))
            : $product->price;
        $salePrice = $isVariant
            ? $this->validSalePrice($regularPrice, $variant['sale_price'] ?? null)
            : $this->validSalePrice($regularPrice, $product->salePrice);
        $inventory = $product->isSellable
            ? ($isVariant ? max(0, (int) ($variant['inventory'] ?? 0)) : max(0, $product->inventory))
            : 0;
        [$brand, $approvedUnbranded, $brandDerivedFromTitle] = $this->brand($product, $variantSku);
        [$description, $descriptionDerivedFromFacts] = $this->description($product);

        $title = $product->title;
        if ($isVariant && $variantName !== '' && ! str_contains(Str::lower($title), Str::lower($variantName))) {
            $title = trim($title.' - '.$variantName);
        }

        $values = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'availability' => $inventory > 0 ? 'in stock' : 'out of stock',
            'condition' => $product->condition,
            'link' => $product->productUrl,
            'image_link' => $product->imageUrl,
            'brand' => $brand,
            'price' => $this->money($regularPrice, $product->currency),
            'google_product_category' => $this->taxonomy($product, 'google_product_category_map'),
            'fb_product_category' => $this->taxonomy($product, 'fb_product_category_map'),
            'quantity_to_sell_on_facebook' => (string) $inventory,
            'sale_price' => $salePrice === null ? '' : $this->money($salePrice, $product->currency),
            'sale_price_effective_date' => $salePrice === null
                ? ''
                : $this->attribute($attributes, ['sale_price_effective_date']),
            'item_group_id' => $isVariant ? $product->externalId : '',
            'gender' => $this->enumAttribute($attributes, ['gender', 'gioi_tinh'], ['male', 'female', 'unisex']),
            'color' => $this->attribute($attributes, ['color', 'mau', 'mau_sac']),
            'size' => $this->attribute($attributes, ['size', 'kich_thuoc']),
            'age_group' => $this->enumAttribute(
                $attributes,
                ['age_group', 'nhom_tuoi'],
                ['newborn', 'infant', 'toddler', 'kids', 'adult'],
            ),
            'material' => $this->attribute($attributes, ['material', 'chat_lieu']),
            'pattern' => $this->attribute($attributes, ['pattern', 'hoa_tiet']),
            'shipping' => trim((string) config('catalog.meta_catalog.shipping', '')),
            'shipping_weight' => $product->weightGrams !== null && $product->weightGrams > 0
                ? $product->weightGrams.' g'
                : '',
            'offer_disclaimer' => $this->attribute($attributes, ['offer_disclaimer']),
            'offer_disclaimer_url' => $this->attribute($attributes, ['offer_disclaimer_url']),
            'video[0].url' => $this->attribute($attributes, ['video_0_url', 'video_url']),
            'video[0].tag[0]' => $this->attribute($attributes, ['video_0_tag_0', 'video_tag']),
            'gtin' => ! $isVariant
                && (bool) config('catalog.meta_catalog.use_confirmed_barcodes_as_gtin', false)
                && $this->validGtin($product->barcode)
                ? $product->barcode
                : '',
            'product_tags[0]' => $this->attribute($attributes, ['product_tags_0', 'product_tag_0']),
            'product_tags[1]' => $this->attribute($attributes, ['product_tags_1', 'product_tag_1']),
            'style[0]' => $this->attribute($attributes, ['style_0', 'style', 'phong_cach']),
        ];

        return new MetaCatalogItem(
            productId: $product->id,
            values: $values,
            approvedUnbranded: $approvedUnbranded,
            variant: $isVariant,
            brandDerivedFromTitle: $brandDerivedFromTitle,
            descriptionDerivedFromFacts: $descriptionDerivedFromFacts,
        );
    }

    /** @return array{string,bool,bool} */
    private function brand(CatalogProductData $product, string $variantSku): array
    {
        if ($product->brand !== '') {
            return [$product->brand, false, false];
        }

        if ((bool) config('catalog.meta_catalog.derive_brand_from_title', true)) {
            $derived = $this->brandFromTitle($product->title);
            if ($derived !== '') {
                return [$derived, false, true];
            }
        }

        $approvedSkus = array_map(
            fn (mixed $sku): string => Str::lower(trim((string) $sku)),
            (array) config('catalog.meta_catalog.unbranded_skus', []),
        );
        $candidateSkus = array_filter([
            Str::lower($variantSku),
            Str::lower($product->sku),
        ]);
        if (array_intersect($candidateSkus, $approvedSkus) === []) {
            return ['', false, false];
        }

        $label = trim((string) config('catalog.meta_catalog.unbranded_label', 'Unbranded'));

        return [in_array($label, ['Generic', 'Unbranded'], true) ? $label : 'Unbranded', true, false];
    }

    private function brandFromTitle(string $title): string
    {
        $title = $this->plainText($title);
        if ($title === '') {
            return '';
        }

        $bestBrand = '';
        $bestOffset = PHP_INT_MAX;
        $bestLength = -1;
        foreach ((array) config('catalog.meta_catalog.brand_aliases', []) as $brand => $aliases) {
            $brand = trim((string) $brand);
            if ($brand === '') {
                continue;
            }

            $aliases = is_array($aliases) ? $aliases : [$aliases];
            foreach (array_unique(array_merge([$brand], $aliases)) as $alias) {
                $alias = trim((string) $alias);
                if ($alias === '') {
                    continue;
                }

                $pattern = '/(?<![\\pL\\pN])'.preg_quote($alias, '/').'(?![\\pL\\pN])/iu';
                if (preg_match($pattern, $title, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                    continue;
                }

                $offset = (int) $matches[0][1];
                $length = strlen($alias);
                if ($offset < $bestOffset || ($offset === $bestOffset && $length > $bestLength)) {
                    $bestBrand = $brand;
                    $bestOffset = $offset;
                    $bestLength = $length;
                }
            }
        }

        return $bestBrand;
    }

    /** @return array{string,bool} */
    private function description(CatalogProductData $product): array
    {
        $description = $this->plainText($product->description);
        if ($description !== '') {
            return [$description, false];
        }
        if (! (bool) config('catalog.meta_catalog.derive_description_from_catalog_facts', true)) {
            return ['', false];
        }

        $title = $this->plainText($product->title);
        $category = $this->plainText($product->categoryPath ?: $product->categoryName);
        $sku = $this->plainText($product->sku);
        if ($title === '' || ($category === '' && $sku === '')) {
            return ['', false];
        }

        $facts = [$title];
        if ($category !== '') {
            $facts[] = 'Danh mục: '.$category;
        }
        if ($sku !== '') {
            $facts[] = 'Mã sản phẩm: '.$sku;
        }

        return [implode('. ', array_map(fn (string $fact): string => rtrim($fact, " .\t\n\r\0\x0B"), $facts)).'.', true];
    }

    private function plainText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/u', '', $value) ?? '';

        return trim(preg_replace('/\\s+/u', ' ', $value) ?? '');
    }

    private function taxonomy(CatalogProductData $product, string $configKey): string
    {
        $map = (array) config('catalog.meta_catalog.'.$configKey, []);
        foreach ([(string) $product->categoryId, $product->categoryPath] as $key) {
            if ($key !== '' && array_key_exists($key, $map)) {
                return trim((string) $map[$key]);
            }
        }

        return '';
    }

    /** @param array<string, mixed> $attributes */
    private function normalizeAttributes(array $attributes): array
    {
        $normalized = [];
        foreach ($attributes as $key => $value) {
            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }
            $normalized[Str::slug((string) $key, '_')] = trim((string) $value);
        }

        return $normalized;
    }

    /** @param array<string, string> $attributes */
    private function attribute(array $attributes, array $aliases): string
    {
        foreach ($aliases as $alias) {
            $value = trim((string) ($attributes[$alias] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /** @param array<string, string> $attributes */
    private function enumAttribute(array $attributes, array $aliases, array $allowed): string
    {
        $value = Str::lower($this->attribute($attributes, $aliases));

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function money(int $amount, string $currency): string
    {
        return $amount > 0 ? $amount.' '.strtoupper(trim($currency)) : '';
    }

    private function validSalePrice(int $regularPrice, mixed $salePrice): ?int
    {
        $salePrice = $salePrice === null ? null : max(0, (int) $salePrice);

        return $salePrice !== null && $salePrice > 0 && $salePrice < $regularPrice ? $salePrice : null;
    }

    private function validGtin(string $value): bool
    {
        $value = trim($value);
        if (! ctype_digit($value) || ! in_array(strlen($value), [8, 12, 13, 14], true)) {
            return false;
        }

        $digits = array_map('intval', str_split($value));
        $check = array_pop($digits);
        $sum = 0;
        $reversePosition = 1;
        foreach (array_reverse($digits) as $digit) {
            $sum += $digit * ($reversePosition % 2 === 1 ? 3 : 1);
            $reversePosition++;
        }

        return (10 - ($sum % 10)) % 10 === $check;
    }

    private function metaItem(object $item): MetaCatalogItem
    {
        if (! $item instanceof MetaCatalogItem) {
            throw new InvalidArgumentException('Meta feed strategy received an unsupported item.');
        }

        return $item;
    }
}
