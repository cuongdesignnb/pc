<?php

namespace App\Services\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateExternalOrder;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;

class CommissionService
{
    public function __construct(private readonly CommissionRules $rules) {}

    public function createForOrder(Order $order): ?AffiliateCommission
    {
        if (! $order->affiliate_id) {
            return null;
        }
        $order->loadMissing('items.product.category.parent.parent.parent');

        $breakdown = [];
        $base = 0;
        $amount = 0;
        $perOrderRulesSeen = [];
        foreach ($order->items as $item) {
            $rule = $this->rules->forProduct($item->product);
            $lineAmount = $this->rules->amount($rule, (int) $item->price, (int) $item->quantity);
            // A fixed "per order" rule (sửa chữa 50k/đơn) pays once per order.
            if ($rule && $rule['type'] === 'fixed' && $rule['per'] === 'order') {
                $key = json_encode($rule);
                $lineAmount = isset($perOrderRulesSeen[$key]) ? 0 : $lineAmount;
                $perOrderRulesSeen[$key] = true;
            }
            $base += (int) $item->total;
            $amount += $lineAmount;
            $breakdown[] = [
                'product_name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'line_total' => (int) $item->total,
                'rule' => $this->rules->describe($rule, (int) $item->price),
                'amount' => $lineAmount,
            ];
        }

        return AffiliateCommission::firstOrCreate(['order_id' => $order->id], [
            'affiliate_id' => $order->affiliate_id,
            'base_amount' => $base,
            'amount' => $amount,
            'breakdown' => $breakdown,
            'status' => 'pending',
        ]);
    }

    public function createForExternalOrder(AffiliateExternalOrder $external): AffiliateCommission
    {
        $external->loadMissing('category.parent.parent.parent');
        $rule = $this->rules->forCategory($external->category);
        $amount = $this->rules->amount($rule, (int) $external->amount, 1);

        return AffiliateCommission::firstOrCreate(['external_order_id' => $external->id], [
            'affiliate_id' => $external->affiliate_id,
            'base_amount' => (int) $external->amount,
            'amount' => $amount,
            'breakdown' => [[
                'product_name' => 'Đơn ngoài web'.($external->kiot_invoice_code ? " {$external->kiot_invoice_code}" : ''),
                'quantity' => 1,
                'line_total' => (int) $external->amount,
                'rule' => $this->rules->describe($rule, (int) $external->amount),
                'amount' => $amount,
            ]],
            'status' => 'pending',
            // Sold in the shop or delivered already: the window starts at the sale.
            'approve_after' => Carbon::parse($external->sold_at)->addDays($this->approveDays()),
        ]);
    }

    /** Keep the commission in step with its order's status. */
    public function syncWithOrder(Order $order): void
    {
        $commission = AffiliateCommission::where('order_id', $order->id)->first();
        if (! $commission || $commission->status === 'cancelled') {
            return;
        }

        if ($order->order_status === 'cancelled') {
            $this->cancel($commission, 'Đơn hàng đã huỷ');
        } elseif ($order->order_status === 'delivered' && $commission->status === 'pending') {
            $commission->update([
                'approve_after' => ($order->delivered_at ?? now())->copy()->addDays($this->approveDays()),
            ]);
        }
    }

    /** Pending commissions whose approval window has passed become withdrawable. */
    public function approveDue(): int
    {
        $approved = 0;
        AffiliateCommission::with('order')
            ->where('status', 'pending')
            ->whereNotNull('approve_after')
            ->where('approve_after', '<=', now())
            ->each(function (AffiliateCommission $commission) use (&$approved): void {
                // Some status changes are written with the query builder and
                // skip model events, so look at the order once more.
                if ($commission->order?->order_status === 'cancelled') {
                    $this->cancel($commission, 'Đơn hàng đã huỷ');

                    return;
                }
                if ($commission->order && $commission->order->order_status !== 'delivered') {
                    return;
                }
                $commission->update(['status' => 'approved', 'approved_at' => now()]);
                $approved++;
            });

        return $approved;
    }

    public function approve(AffiliateCommission $commission, ?User $by): void
    {
        if ($commission->status !== 'pending') {
            return;
        }
        $commission->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $by?->id]);
    }

    public function cancel(AffiliateCommission $commission, string $reason): void
    {
        $commission->update(['status' => 'cancelled', 'cancelled_reason' => mb_substr($reason, 0, 255)]);
    }

    /** @return array{available: int, pending: int, next_approval_at: string|null, total_earned: int} */
    public function wallet(Affiliate $affiliate): array
    {
        $commissions = $affiliate->commissions()->whereIn('status', ['pending', 'approved'])->get(['status', 'amount', 'approve_after']);
        $pending = $commissions->where('status', 'pending');
        $next = $pending->pluck('approve_after')->filter()->min();

        return [
            'available' => (int) $commissions->where('status', 'approved')->sum('amount'),
            'pending' => (int) $pending->sum('amount'),
            'next_approval_at' => $next?->toIso8601String(),
            'total_earned' => (int) $commissions->sum('amount'),
        ];
    }

    public function approveDays(): int
    {
        return max(0, (int) Setting::get('affiliate_approve_days', 7));
    }
}
