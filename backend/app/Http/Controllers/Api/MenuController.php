<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    /**
     * Get menu by location (header, footer, etc.)
     */
    public function byLocation(string $location): JsonResponse
    {
        $menu = Menu::where('location', $location)
            ->where('is_active', true)
            ->first();

        if (!$menu) {
            return response()->json(['items' => []]);
        }

        $items = $menu->items()
            ->where('is_active', true)
            ->with([
                'category',
                'children' => function ($q) {
                    $q->where('is_active', true)
                        ->orderBy('sort_order')
                        ->with([
                            'category',
                            'children' => function ($q2) {
                                $q2->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->with([
                                        'category',
                                        'children' => function ($q3) {
                                            $q3->where('is_active', true)
                                                ->orderBy('sort_order')
                                                ->with('category');
                                        }
                                    ]);
                            }
                        ]);
                }
            ])
            ->get();

        return response()->json([
            'menu' => [
                'id'   => $menu->id,
                'name' => $menu->name,
                'slug' => $menu->slug,
            ],
            'items' => $items,
        ]);
    }
}
