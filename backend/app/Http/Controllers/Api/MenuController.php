<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Menu;
use App\Support\PublicAssetUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

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

        $items = $menu?->items()
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
                                        },
                                    ]);
                            },
                        ]);
                },
            ])
            ->get() ?? collect();

        if ($items->isEmpty()) {
            return response()->json([
                'menu' => [
                    'id' => $menu?->id ?? 0,
                    'name' => $menu?->name ?? 'Danh mục sản phẩm',
                    'slug' => $menu?->slug ?? 'synced-categories',
                ],
                'items' => $this->syncedCategoryItems(),
            ]);
        }

        return response()->json([
            'menu' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'slug' => $menu->slug,
            ],
            'items' => $items,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function syncedCategoryItems(): array
    {
        $categories = Category::query()
            ->visibleOnStorefront()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $grouped = $categories->groupBy(fn (Category $category): string => (string) ($category->parent_id ?? 0));

        $build = function (?int $parentId) use (&$build, $grouped): array {
            /** @var Collection<int, Category> $children */
            $children = $grouped->get((string) ($parentId ?? 0), collect());

            return $children->map(function (Category $category) use (&$build): array {
                return [
                    'id' => $category->id,
                    'title' => $category->name,
                    'url' => null,
                    'type' => 'category',
                    'category' => ['slug' => $category->slug],
                    'icon' => PublicAssetUrl::normalize($category->icon),
                    'badge_text' => null,
                    'badge_color' => null,
                    'css_class' => null,
                    'target' => '_self',
                    'sort_order' => (int) $category->sort_order,
                    'is_active' => true,
                    'is_mega' => $category->parent_id === null,
                    'mega_columns' => 3,
                    'description' => $category->description,
                    'image' => PublicAssetUrl::normalize($category->image),
                    'children' => $build($category->id),
                ];
            })->values()->all();
        };

        return $build(null);
    }
}
