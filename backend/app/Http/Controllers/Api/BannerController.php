<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    /**
     * GET /api/v1/banners?position=hero
     * Returns active banners, optionally filtered by position.
     */
    public function index(Request $request)
    {
        $query = Banner::active()->orderBy('sort_order');

        if ($request->has('position')) {
            $query->where('position', $request->input('position'));
        }

        $banners = $query->get()->map(function ($banner) {
            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'description' => $banner->description,
                'badge' => $banner->badge,
                'image' => $banner->image,
                'link' => $banner->link,
                'position' => $banner->position,
                'sort_order' => $banner->sort_order,
                'metadata' => $banner->metadata,
            ];
        });

        return response()->json($banners);
    }
}
