<?php

namespace App\Services\Payments;

/**
 * Deposit rule set on a product, a category or a province (Cài đặt → Thanh toán).
 *
 *   none:    no deposit for this product/category/province
 *   percent: value % of the money it applies to
 *   fixed:   value đ (per unit on a product/category, once per order on a province)
 *
 * Null means "not set here": the next level decides.
 */
final class DepositRules
{
    public const TYPES = ['none', 'percent', 'fixed'];

    /** @return array{type: string, value: float}|null */
    public static function normalise(mixed $rule): ?array
    {
        if (! is_array($rule) || ! in_array($rule['type'] ?? null, self::TYPES, true)) {
            return null;
        }
        if ($rule['type'] === 'none') {
            return ['type' => 'none', 'value' => 0];
        }
        $value = max(0, (float) ($rule['value'] ?? 0));
        if ($value <= 0) {
            return null;
        }

        return [
            'type' => $rule['type'],
            'value' => $rule['type'] === 'percent' ? min($value, 100) : (int) round($value),
        ];
    }

    /** Validation for the "Đặt cọc" field on category and product forms. */
    public static function validationRules(): array
    {
        return [
            'deposit_rule' => 'nullable|array',
            'deposit_rule.type' => 'nullable|in:none,percent,fixed',
            'deposit_rule.value' => 'nullable|numeric|min:0|max:1000000000',
        ];
    }

    /**
     * Province rules keyed by province code. Besides the deposit rule a
     * province may turn COD off ("cod": false): those customers transfer a
     * deposit or the whole order.
     *
     * @return array<string, array{type: string|null, value: float, cod: bool}>
     */
    public static function normaliseProvinces(mixed $rules): array
    {
        if (is_string($rules)) {
            $rules = json_decode($rules, true);
        }
        $out = [];
        foreach (is_array($rules) ? $rules : [] as $code => $rule) {
            $code = trim((string) (is_array($rule) && isset($rule['code']) ? $rule['code'] : $code));
            if ($code === '' || ! preg_match('/^\d{1,3}$/', $code) || ! is_array($rule)) {
                continue;
            }
            $deposit = self::normalise($rule);
            $cod = ! array_key_exists('cod', $rule) || filter_var($rule['cod'], FILTER_VALIDATE_BOOLEAN);
            if ($deposit === null && $cod) {
                continue;
            }
            $out[$code] = [
                'type' => $deposit['type'] ?? null,
                'value' => $deposit['value'] ?? 0,
                'cod' => $cod,
            ];
        }
        ksort($out);

        return $out;
    }
}
