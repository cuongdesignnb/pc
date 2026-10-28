<?php

namespace App\Services\Payments;

use App\Models\Setting;

/**
 * How much a customer must transfer to hold an order with a deposit
 * (Cài đặt → Thanh toán). Null means a deposit is not offered for that total
 * and the order has to be paid in full.
 */
class DepositPolicy
{
    public function amountFor(int $total): ?int
    {
        if ($total <= 0 || ! filter_var(Setting::get('payment_deposit_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $value = (float) Setting::get('payment_deposit_value', 0);
        $amount = Setting::get('payment_deposit_type', 'percent') === 'fixed'
            ? (int) round($value)
            // Round up to whole thousands so the QR amount is easy to read.
            : (int) (ceil($total * $value / 100 / 1000) * 1000);

        return $amount > 0 && $amount < $total ? $amount : null;
    }
}
