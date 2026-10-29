<?php

namespace App\Services\Affiliate;

use App\Models\Category;
use App\Models\Product;

/**
 * Commission rule set on a product, or on its category or the nearest parent
 * category that has one (same lookup as warranty packages).
 *
 *   percent: value % of the line total (laptop mới 1–2%, linh kiện 5%)
 *   fixed:   value per unit, or once per order when per = "order" (sửa chữa 50k/đơn)
 *   tiers:   amount per unit picked by unit price (laptop cũ 200–500k/máy)
 */
final class CommissionRules
{
    public const TYPES = ['percent', 'fixed', 'tiers'];

    /** @return array{type: string, value: float, per: string, tiers: list<array{min_price: int, amount: int}>}|null */
    public function forProduct(?Product $product): ?array
    {
        return self::normalise($product?->affiliate_commission) ?? $this->forCategory($product?->category);
    }

    /** @return array{type: string, value: float, per: string, tiers: list<array{min_price: int, amount: int}>}|null */
    public function forCategory(?Category $category): ?array
    {
        for ($depth = 0; $category instanceof Category && $depth < 5; $depth++) {
            if ($rule = self::normalise($category->affiliate_commission)) {
                return $rule;
            }
            $category = $category->parent;
        }

        return null;
    }

    /** Validation for the "Hoa hồng CTV" field on category and product forms. */
    public static function validationRules(): array
    {
        return [
            'affiliate_commission' => 'nullable|array',
            'affiliate_commission.type' => 'nullable|in:percent,fixed,tiers',
            'affiliate_commission.value' => 'nullable|numeric|min:0|max:1000000000',
            'affiliate_commission.per' => 'nullable|in:item,order',
            'affiliate_commission.tiers' => 'nullable|array|max:10',
            'affiliate_commission.tiers.*.min_price' => 'required|integer|min:0|max:1000000000',
            'affiliate_commission.tiers.*.amount' => 'required|integer|min:0|max:1000000000',
        ];
    }

    /** Commission for one order line. */
    public function amount(?array $rule, int $unitPrice, int $quantity): int
    {
        if ($rule === null || $quantity < 1) {
            return 0;
        }

        return match ($rule['type']) {
            'percent' => (int) round($unitPrice * $quantity * $rule['value'] / 100),
            'fixed' => (int) round($rule['value']) * ($rule['per'] === 'order' ? 1 : $quantity),
            'tiers' => $this->tierAmount($rule['tiers'], $unitPrice) * $quantity,
            default => 0,
        };
    }

    /** "5%", "50.000đ/đơn", "200.000đ – 500.000đ/máy" for the CTV share box. */
    public function describe(?array $rule, ?int $unitPrice = null): ?string
    {
        if ($rule === null) {
            return null;
        }
        $money = fn (int $value): string => number_format($value, 0, ',', '.').'đ';

        return match ($rule['type']) {
            'percent' => rtrim(rtrim(number_format($rule['value'], 2, ',', '.'), '0'), ',').'%',
            'fixed' => $money((int) $rule['value']).($rule['per'] === 'order' ? '/đơn' : '/sản phẩm'),
            'tiers' => $unitPrice !== null
                ? $money($this->tierAmount($rule['tiers'], $unitPrice)).'/sản phẩm'
                : $money((int) collect($rule['tiers'])->min('amount')).' – '.$money((int) collect($rule['tiers'])->max('amount')).'/sản phẩm',
            default => null,
        };
    }

    /** @return array{type: string, value: float, per: string, tiers: list<array{min_price: int, amount: int}>}|null */
    public static function normalise(mixed $rule): ?array
    {
        if (! is_array($rule) || ! in_array($rule['type'] ?? null, self::TYPES, true)) {
            return null;
        }
        $tiers = collect(is_array($rule['tiers'] ?? null) ? $rule['tiers'] : [])
            ->filter(fn ($tier): bool => is_array($tier) && (int) ($tier['amount'] ?? 0) > 0)
            ->map(fn (array $tier): array => [
                'min_price' => max(0, (int) ($tier['min_price'] ?? 0)),
                'amount' => (int) $tier['amount'],
            ])
            ->unique('min_price')
            ->sortBy('min_price')
            ->values()
            ->all();
        $value = max(0, (float) ($rule['value'] ?? 0));
        if ($rule['type'] === 'tiers' ? $tiers === [] : $value <= 0) {
            return null;
        }

        return [
            'type' => $rule['type'],
            'value' => $rule['type'] === 'percent' ? min($value, 100) : $value,
            'per' => ($rule['per'] ?? 'item') === 'order' ? 'order' : 'item',
            'tiers' => $rule['type'] === 'tiers' ? $tiers : [],
        ];
    }

    /** @param list<array{min_price: int, amount: int}> $tiers */
    private function tierAmount(array $tiers, int $unitPrice): int
    {
        $amount = 0;
        foreach ($tiers as $tier) {
            if ($unitPrice >= $tier['min_price']) {
                $amount = $tier['amount'];
            }
        }

        return $amount;
    }
}
