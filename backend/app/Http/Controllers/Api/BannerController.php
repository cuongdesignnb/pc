<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Support\PublicAssetUrl;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    /**
     * GET /api/v1/banners?position=hero&category_slug=vga
     * Returns active banners, optionally filtered by position/category.
     */
    public function index(Request $request)
    {
        $query = Banner::active()->orderBy('sort_order');

        if ($request->has('position')) {
            $query->where('position', $request->input('position'));
        }

        $categorySlug = trim((string) $request->input('category_slug', ''));

        $banners = $query->get()
            ->when($categorySlug !== '', function ($collection) use ($categorySlug) {
                return $collection->filter(function ($banner) use ($categorySlug) {
                    $configuredSlug = data_get($banner->metadata, 'category_slug');
                    if (is_array($configuredSlug)) {
                        return in_array($categorySlug, $configuredSlug, true);
                    }

                    return is_string($configuredSlug) && trim($configuredSlug) === $categorySlug;
                });
            })
            ->values()
            ->map(function ($banner) {
            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'description' => $banner->description,
                'badge' => $banner->badge,
                'image' => PublicAssetUrl::normalize($banner->image),
                'link' => $banner->link,
                'position' => $banner->position,
                'sort_order' => $banner->sort_order,
                'metadata' => $banner->metadata,
            ];
        });

        return response()->json($banners);
    }
}
