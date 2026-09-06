<?php

namespace Database\Seeders;

use App\Models\BuildPreset;
use App\Models\ComponentType;
use App\Models\Product;
use App\Services\PcBuilder\PcBuilderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class BuildPresetSeeder extends Seeder
{
    /**
     * Refresh the catalog-backed presets without embedding product IDs.
     *
     * Presets are editorial records, but their products, image and price must
     * always be resolved from the products currently sellable in the catalog.
     * The same builder service used by the API is used here so a preset cannot
     * be seeded with a combination the public builder would reject.
     */
    public function run(): void
    {
        $types = ComponentType::query()
            ->orderBy('display_order')
            ->get()
            ->keyBy('slug');

        if ($types->isEmpty()) {
            return;
        }

        $productsByType = $types->mapWithKeys(function (ComponentType $type): array {
            $products = Product::query()
                ->with(['images', 'specifications.specificationKey', 'powerRequirement'])
                ->sellableOnline()
                ->where('component_type_id', $type->id)
                ->orderByDesc('is_featured')
                ->orderByDesc('sold_count')
                ->orderBy('id')
                ->limit(64)
                ->get();

            return [(int) $type->id => $products];
        });

        $builder = app(PcBuilderService::class);
        $definitions = [
            [
                'name' => 'PC Gaming',
                'slug' => 'pc-gaming',
                'usage_type' => 'gaming',
                'description' => 'Cấu hình cân bằng cho game thủ.',
                'optional_types' => ['vga', 'cooler'],
                'strategy' => 'popular',
            ],
            [
                'name' => 'PC Đồ họa - Render',
                'slug' => 'pc-do-hoa-render',
                'usage_type' => 'render',
                'description' => 'Cấu hình ưu tiên năng lực xử lý đồ họa và render.',
                'optional_types' => ['vga', 'cooler'],
                'strategy' => 'performance',
            ],
            [
                'name' => 'PC Văn phòng',
                'slug' => 'pc-van-phong',
                'usage_type' => 'office',
                'description' => 'Cấu hình gọn gàng cho công việc hằng ngày.',
                'optional_types' => ['cooler'],
                'strategy' => 'value',
            ],
            [
                'name' => 'PC Streamer',
                'slug' => 'pc-streamer',
                'usage_type' => 'streaming',
                'description' => 'Cấu hình cho chơi game và phát trực tiếp.',
                'optional_types' => ['vga', 'cooler'],
                'strategy' => 'performance',
            ],
        ];

        foreach ($definitions as $index => $definition) {
            $build = $this->selectBuild(
                $types,
                $productsByType,
                $builder,
                $definition,
                (int) $index,
            );

            $check = $builder->checkBuild($build);
            if (! $check['completion']['complete'] || collect($check['issues'])->contains(fn (array $issue) => $issue['type'] === 'error')) {
                // Do not overwrite a previously valid preset with an empty or
                // invalid combination when the catalog is temporarily sparse.
                continue;
            }

            $products = collect($build)->mapWithKeys(function (int $productId, int $typeId) use ($types): array {
                $type = $types->first(fn (ComponentType $candidate) => (int) $candidate->id === $typeId);

                return $type ? [$type->slug => $productId] : [];
            })->all();

            $image = $this->presetImage($build, $productsByType);

            BuildPreset::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'image' => $image,
                    'usage_type' => $definition['usage_type'],
                    'products' => $products,
                    'starting_price' => $check['totals']['price'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }

    /**
     * Select a complete build from current sellable products.
     *
     * The dependency-friendly order lets the server reject a wrong socket,
     * memory type, case size or cooler fit before the next part is selected.
     * Required types outside the known order are still included dynamically.
     *
     * @param  Collection<string, ComponentType>  $types
     * @param  Collection<int, Collection<int, Product>>  $productsByType
     * @param  array<string, mixed>  $definition
     * @return array<int, int>
     */
    private function selectBuild(
        Collection $types,
        Collection $productsByType,
        PcBuilderService $builder,
        array $definition,
        int $presetIndex,
    ): array {
        $preferredOrder = ['cpu', 'mainboard', 'ram', 'case', 'vga', 'cooler', 'ssd', 'hdd', 'fan', 'psu'];
        $knownOrder = collect($preferredOrder)
            ->filter(fn (string $slug) => $types->has($slug))
            ->values();
        $remaining = $types
            ->keys()
            ->reject(fn (string $slug) => $knownOrder->contains($slug))
            ->sortBy(fn (string $slug) => $types->get($slug)->display_order)
            ->values();

        $required = $types
            ->filter(fn (ComponentType $type) => $type->is_required)
            ->sortBy('display_order')
            ->keys();
        $optional = collect($definition['optional_types'] ?? [])
            ->filter(fn (string $slug) => $types->has($slug))
            ->values();
        $targetSlugs = $knownOrder
            ->merge($remaining)
            ->filter(fn (string $slug) => $required->contains($slug) || $optional->contains($slug))
            ->unique()
            ->values();

        // Keep every required type in the build even if an administrator has
        // added a new type that is not part of the standard order above.
        $targetSlugs = $targetSlugs->merge($required)->unique()->values();
        $selected = [];
        $fallbackPsu = null;

        foreach ($targetSlugs as $slug) {
            $type = $types->get($slug);
            if (! $type) {
                continue;
            }

            $candidates = $this->orderedCandidates(
                $productsByType->get((int) $type->id, collect()),
                (string) $definition['strategy'],
                $presetIndex,
                $slug,
            );
            if ($candidates->isEmpty()) {
                continue;
            }

            foreach ($candidates as $candidate) {
                $candidateBuild = $selected + [(int) $type->id => (int) $candidate->id];
                $candidateCheck = $builder->checkBuild($candidateBuild);
                $hasError = collect($candidateCheck['issues'])->contains(fn (array $issue) => $issue['type'] === 'error');

                if ($slug === 'psu') {
                    if (! $hasError && ! collect($candidateCheck['issues'])->contains(fn (array $issue) => $issue['code'] === 'psu_insufficient')) {
                        $selected = $candidateBuild;
                        $fallbackPsu = null;
                        break;
                    }
                    if (! $hasError && $fallbackPsu === null) {
                        $fallbackPsu = $candidateBuild;
                    }

                    continue;
                }

                if (! $hasError) {
                    $selected = $candidateBuild;
                    break;
                }
            }

            if ($slug === 'psu' && $fallbackPsu !== null) {
                $selected = $fallbackPsu;
            }
        }

        return $selected;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    private function orderedCandidates(Collection $products, string $strategy, int $presetIndex, string $typeSlug): Collection
    {
        $ordered = match ($strategy) {
            'value' => $products->sortBy(fn (Product $product) => $product->purchasableUnitPrice())->values(),
            'performance' => $products->sortByDesc(fn (Product $product) => $product->purchasableUnitPrice())->values(),
            default => $products->values(),
        };

        if ($typeSlug === 'psu') {
            $ordered = $ordered->sortBy(fn (Product $product) => $this->numericSpec($product, 'wattage'))->values();
        }

        if ($ordered->isEmpty()) {
            return $ordered;
        }

        $offset = $presetIndex % $ordered->count();

        return $ordered->slice($offset)->concat($ordered->slice(0, $offset))->values();
    }

    /** @param array<int, int> $build */
    private function presetImage(array $build, Collection $productsByType): ?string
    {
        foreach ($build as $typeId => $productId) {
            $image = $productsByType
                ->get((int) $typeId, collect())
                ->firstWhere('id', (int) $productId)
                ?->images
                ?->first();
            if ($image?->url) {
                return $image->url;
            }
        }

        return null;
    }

    private function numericSpec(Product $product, string $key): int
    {
        $specification = $product->specifications->first(
            fn ($specification) => $specification->specificationKey?->key === $key,
        );

        return (int) ($specification?->value ?? 0);
    }
}
