<?php

namespace App\Services\Catalog\Meta;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Writes the subsets listed in catalog.meta_catalog.subsets from a freshly
 * built Meta feed: same columns and rows, keeping only in-stock items of the
 * subset's category tree.
 */
class MetaCatalogSubsetWriter
{
    public static function artifact(string $name): string
    {
        return 'meta-'.$name.'.csv';
    }

    public function write(string $feedPath): void
    {
        foreach ((array) config('catalog.meta_catalog.subsets', []) as $name => $subset) {
            $this->writeSubset($feedPath, (string) $name, (string) ($subset['category_slug'] ?? ''));
        }
    }

    private function writeSubset(string $feedPath, string $name, string $categorySlug): void
    {
        $ids = $this->externalIds($categorySlug);
        $disk = Storage::disk((string) config('catalog.feed_disk', 'local'));
        $directory = trim((string) config('catalog.feed_directory', 'catalog-feeds'), '/');
        $final = $disk->path($directory.'/'.self::artifact($name));
        $temporary = $disk->path($directory.'/.'.self::artifact($name).'.'.Str::uuid().'.tmp');

        $in = fopen($feedPath, 'rb');
        $out = fopen($temporary, 'wb');
        try {
            $headers = fgetcsv($in, null, ',', '"', '');
            fputcsv($out, $headers, ',', '"', '');
            $idColumn = array_search('id', $headers, true);
            $availabilityColumn = array_search('availability', $headers, true);
            while (($row = fgetcsv($in, null, ',', '"', '')) !== false) {
                // Variant rows look like kiot:123:variant:4; match their product.
                $productId = explode(':variant:', (string) ($row[$idColumn] ?? ''))[0];
                if (($row[$availabilityColumn] ?? '') === 'in stock' && isset($ids[$productId])) {
                    fputcsv($out, $row, ',', '"', '');
                }
            }
        } finally {
            fclose($in);
            fclose($out);
        }
        if (! @rename($temporary, $final)) {
            @unlink($temporary);
        }
    }

    /** Feed ids (kiot:… or sku:…) of the products in the category and its sub-categories. */
    private function externalIds(string $categorySlug): array
    {
        $root = $categorySlug === '' ? null : Category::where('slug', $categorySlug)->first();
        if (! $root) {
            return [];
        }
        $categoryIds = [$root->id];
        for ($level = [$root->id], $depth = 0; $level !== [] && $depth < 5; $depth++) {
            $level = Category::whereIn('parent_id', $level)->pluck('id')->all();
            $categoryIds = array_merge($categoryIds, $level);
        }

        $ids = [];
        Product::whereIn('category_id', $categoryIds)->get(['id', 'remote_product_id', 'sku'])
            ->each(function (Product $product) use (&$ids): void {
                $id = $product->catalogContentId();
                if ($id !== '') {
                    $ids[$id] = true;
                }
            });

        return $ids;
    }
}
