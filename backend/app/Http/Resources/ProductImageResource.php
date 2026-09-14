<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin \App\Models\ProductImage */
class ProductImageResource extends JsonResource
{
    /**
     * Keep provider/source records out of the public contract until they have
     * a usable public URL, and preserve the product's primary/sort order.
     */
    public static function usable(Collection $images): Collection
    {
        return $images
            ->filter(function ($image): bool {
                $url = $image->url;

                if (! is_string($url) || trim($url) === '') {
                    return false;
                }

                $parts = parse_url(trim($url));
                if ($parts === false) {
                    return false;
                }

                if (isset($parts['scheme'])) {
                    return in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
                        && ! isset($parts['user'], $parts['pass']);
                }

                return str_starts_with(trim($url), '/') && ! str_starts_with(trim($url), '//');
            })
            ->unique(fn ($image): string => trim((string) $image->url))
            ->values();
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
        ];
    }
}
