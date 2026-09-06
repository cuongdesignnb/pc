<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ComponentType;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BuilderCatalogSeeder extends Seeder
{
    /**
     * Existing stores may have products linked to categories but not yet to
     * the PC Builder component type column. These aliases describe category
     * terminology only; no product, price or inventory data is embedded here.
     */
    private const CATEGORY_ALIASES = [
        'cpu' => ['cpu', 'bo vi xu ly'],
        'mainboard' => ['mainboard', 'main board', 'bo mach chu'],
        'ram' => ['ram', 'bo nho trong'],
        'vga' => ['vga', 'card man hinh', 'card do hoa'],
        'ssd' => ['ssd'],
        'hdd' => ['hdd'],
        'psu' => ['psu', 'nguon may tinh'],
        'case' => ['case', 'vo may tinh', 'vo case'],
        'cooler' => ['cooler', 'tan nhiet', 'cooling'],
        'fan' => ['fan', 'quat tan nhiet', 'quat case'],
    ];

    private const NON_DESKTOP_TERMS = ['laptop', 'notebook', 'macbook'];

    public function run(): void
    {
        $types = ComponentType::query()->get()->keyBy('slug');
        if ($types->isEmpty()) {
            return;
        }

        $categories = Category::query()
            ->get(['id', 'parent_id', 'component_type_id', 'name', 'slug'])
            ->keyBy('id');

        // Respect an explicit admin/category mapping first, then fill only
        // unmapped categories whose names clearly identify a builder slot.
        foreach ($types as $slug => $type) {
            foreach ($categories as $category) {
                if ($category->component_type_id || ! $this->matchesType($category, (string) $slug)) {
                    continue;
                }

                $category->update(['component_type_id' => $type->id]);
                $category->component_type_id = $type->id;
            }
        }

        $categoryTypeIds = $this->resolveCategoryTypes(
            $categories,
            $types->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );
        foreach ($categoryTypeIds as $typeId => $categoryIds) {
            Product::query()
                ->whereNull('component_type_id')
                ->whereIn('category_id', $categoryIds)
                ->update(['component_type_id' => $typeId]);
        }
    }

    private function matchesType(Category $category, string $typeSlug): bool
    {
        $text = $this->normalize(implode(' ', [
            (string) $category->name,
            (string) $category->slug,
        ]));

        foreach (self::NON_DESKTOP_TERMS as $term) {
            if ($this->containsPhrase($text, $term)) {
                return false;
            }
        }

        foreach (self::CATEGORY_ALIASES[$typeSlug] ?? [$typeSlug] as $alias) {
            if ($this->containsPhrase($text, $alias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a category's own type or an explicitly/automatically typed
     * ancestor so products in a nested catalog category are included too.
     *
     * @param  \Illuminate\Support\Collection<int, Category>  $categories
     * @param  array<int, int>  $validTypeIds
     * @return array<int, array<int, int>>
     */
    private function resolveCategoryTypes(Collection $categories, array $validTypeIds): array
    {
        $resolved = [];

        foreach ($categories as $category) {
            $current = $category;
            $visited = [];

            while ($current && ! isset($visited[$current->id])) {
                $visited[$current->id] = true;
                if ($current->component_type_id && in_array((int) $current->component_type_id, $validTypeIds, true)) {
                    $resolved[(int) $current->component_type_id][] = (int) $category->id;
                    break;
                }

                $current = $categories->get($current->parent_id);
            }
        }

        foreach ($resolved as $typeId => $categoryIds) {
            $resolved[$typeId] = array_values(array_unique($categoryIds));
        }

        return $resolved;
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', Str::lower(Str::ascii($value))));
    }

    private function containsPhrase(string $value, string $phrase): bool
    {
        $normalizedPhrase = $this->normalize($phrase);

        return $normalizedPhrase !== ''
            && str_contains(' '.$value.' ', ' '.$normalizedPhrase.' ');
    }
}
