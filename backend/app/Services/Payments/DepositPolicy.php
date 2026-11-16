<?php

namespace App\Services\Payments;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;

/**
 * How much a customer must transfer to hold an order with a deposit
 * (Cài đặt → Thanh toán). Null means a deposit is not offered for that order
 * and it has to be paid in full (or by COD).
 *
 * "Cho phép đặt cọc" (payment_deposit_enabled) is the master switch. With it
 * on, the deposit of an order is worked out in this order:
 *
 *  1. Province rule "Không cọc" (e.g. Hà Nội) → no deposit for the order.
 *  2. Order total under "Không cọc với đơn dưới" → no deposit.
 *  3. Each line: the product's own rule, else the nearest category (or parent
 *     category) rule. "Không cọc" there makes that line need no deposit,
 *     percent takes % of the line, fixed takes the amount per unit.
 *  4. Lines without a product/category rule, plus shipping and minus the CTV
 *     discount, use the province rule (percent/fixed) or else the general rule
 *     (payment_deposit_type/value), the fixed amount counted once per order.
 *  5. The parts are added up; percentages are rounded up to whole thousands.
 *     A deposit that is 0 or not below the order total is not offered.
 *
 * With no province/category/product rules and no minimum set, this is the
 * same amount as before: the general rule on the order total.
 */
class DepositPolicy
{
    /** General rule only, on the order total. */
    public function amountFor(int $total): ?int
    {
        return $this->amountForOrder($total, [], null);
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int, line_total: int}>  $lines
     */
    public function amountForOrder(int $total, array $lines, ?string $provinceCode): ?int
    {
        if ($total <= 0 || ! $this->enabled()) {
            return null;
        }
        $minimum = max(0, (int) Setting::get('payment_deposit_min_total', 0));
        if ($minimum > 0 && $total < $minimum) {
            return null;
        }
        $province = $this->provinceRule($provinceCode);
        if (($province['type'] ?? null) === 'none') {
            return null;
        }

        $products = $lines === [] ? collect() : Product::query()
            ->with('category.parent.parent.parent.parent')
            ->whereIn('id', array_map(fn (array $line): int => (int) $line['product_id'], $lines))
            ->get()
            ->keyBy('id');
        $percent = 0.0;
        $fixed = 0;
        $covered = 0;
        foreach ($lines as $line) {
            $product = $products->get((int) $line['product_id']);
            $rule = $this->lineRule($product);
            if ($rule === null) {
                continue;
            }
            $lineTotal = max(0, (int) $line['line_total']);
            $covered += $lineTotal;
            if ($rule['type'] === 'percent') {
                $percent += $lineTotal * $rule['value'] / 100;
            } elseif ($rule['type'] === 'fixed') {
                $fixed += (int) $rule['value'] * max(1, (int) $line['quantity']);
            }
        }

        $rest = max(0, $total - $covered);
        if ($rest > 0) {
            $rule = $province !== null && $province['type'] !== null ? $province : $this->generalRule();
            if ($rule['type'] === 'percent') {
                $percent += $rest * $rule['value'] / 100;
            } elseif ($rule['type'] === 'fixed') {
                $fixed += (int) round($rule['value']);
            }
        }

        // Round up to whole thousands so the QR amount is easy to read.
        $amount = (int) (ceil(round($percent / 1000, 6)) * 1000) + $fixed;

        return $amount > 0 && $amount < $total ? $amount : null;
    }

    /** False when the shipping province is set to "Bắt buộc chuyển khoản" (no COD). */
    public function codAllowed(?string $provinceCode): bool
    {
        return $this->provinceRule($provinceCode)['cod'] ?? true;
    }

    public function enabled(): bool
    {
        return filter_var(Setting::get('payment_deposit_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array{type: string|null, value: float, cod: bool}|null */
    public function provinceRule(?string $provinceCode): ?array
    {
        $code = trim((string) $provinceCode);
        if ($code === '') {
            return null;
        }

        return DepositRules::normaliseProvinces(Setting::get('payment_deposit_province_rules', []))[$code] ?? null;
    }

    /** @return array{type: string, value: float}|null */
    private function lineRule(?Product $product): ?array
    {
        if ($product === null) {
            return null;
        }
        if ($rule = DepositRules::normalise($product->deposit_rule)) {
            return $rule;
        }
        $category = $product->category;
        for ($depth = 0; $category instanceof Category && $depth < 5; $depth++) {
            if ($rule = DepositRules::normalise($category->deposit_rule)) {
                return $rule;
            }
            $category = $category->parent;
        }

        return null;
    }

    /** @return array{type: string, value: float} */
    private function generalRule(): array
    {
        return [
            'type' => Setting::get('payment_deposit_type', 'percent') === 'fixed' ? 'fixed' : 'percent',
            'value' => (float) Setting::get('payment_deposit_value', 0),
        ];
    }
}
