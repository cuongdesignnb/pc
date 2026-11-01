<?php

use App\Services\Seo\VietnameseSlugNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Products whose slug is longer than 160 characters (very long KIOT names)
 * have no public link: the URL resolver rejects such slugs. Cut them at a
 * word boundary. The old URL never worked, so no redirect is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $slugs = app(VietnameseSlugNormalizer::class);
        $max = VietnameseSlugNormalizer::MAX_LENGTH;

        DB::table('products')->whereRaw('LENGTH(slug) > ?', [$max])->orderBy('id')->get(['id', 'slug'])
            ->each(function (object $product) use ($slugs, $max): void {
                $base = $slugs->shorten((string) $product->slug, $max - strlen('-'.$product->id));
                $slug = $base;
                if ($this->taken($slug, $product->id)) {
                    $slug = $base.'-'.$product->id;
                }
                DB::table('products')->where('id', $product->id)->update(['slug' => $slug, 'updated_at' => now()]);
            });
    }

    public function down(): void
    {
        // Shortened slugs are kept: the long ones had no working URL.
    }

    private function taken(string $slug, int $productId): bool
    {
        return DB::table('products')->where('slug', $slug)->where('id', '!=', $productId)->exists()
            || DB::table('categories')->where('slug', $slug)->exists();
    }
};
