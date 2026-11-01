<?php

namespace App\Services\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Turns a CTV's withdrawable balance into a payout request. Requests are made
 * on the CTV's payout days by the scheduler, or by the CTV from the account
 * page; staff then transfer the money and mark the request paid.
 */
class PayoutService
{
    public function __construct(private readonly CommissionService $commissions) {}

    /** Withdraw the whole available balance. Throws when the CTV cannot withdraw now. */
    public function request(Affiliate $affiliate, string $source = 'manual'): AffiliatePayout
    {
        return DB::transaction(function () use ($affiliate, $source): AffiliatePayout {
            // Lock the CTV so two requests cannot both take the same balance.
            $affiliate = Affiliate::whereKey($affiliate->id)->lockForUpdate()->firstOrFail();
            if (! $affiliate->isActive()) {
                throw new InvalidArgumentException('Tài khoản cộng tác viên chưa hoạt động.');
            }
            if ($affiliate->payouts()->where('status', 'pending')->exists()) {
                throw new InvalidArgumentException('Bạn đang có một lệnh rút chờ chuyển khoản.');
            }
            if (! $affiliate->bank_account_number) {
                throw new InvalidArgumentException('Vui lòng cập nhật tài khoản ngân hàng trước khi rút.');
            }
            $available = $this->commissions->wallet($affiliate)['available'];
            $minimum = $this->minimum();
            if ($available <= 0 || $available < $minimum) {
                throw new InvalidArgumentException('Số dư được rút tối thiểu là '.number_format($minimum, 0, ',', '.').'đ.');
            }

            return $affiliate->payouts()->create([
                'amount' => $available,
                'status' => 'pending',
                'source' => $source,
                'bank_name' => $affiliate->bank_name,
                'bank_account_number' => $affiliate->bank_account_number,
                'bank_account_name' => $affiliate->bank_account_name,
            ]);
        });
    }

    /** Payout requests for every active CTV whose payout day is today. */
    public function createScheduled(?Carbon $today = null): int
    {
        $day = ($today ?? now())->day;
        $created = 0;
        Affiliate::where('status', 'active')->whereNotNull('payout_days')->each(function (Affiliate $affiliate) use ($day, &$created): void {
            if (! in_array($day, array_map('intval', $affiliate->payout_days ?? []), true)) {
                return;
            }
            try {
                $this->request($affiliate, 'schedule');
                $created++;
            } catch (InvalidArgumentException) {
                // Nothing to withdraw yet, or a request is already waiting.
            }
        });

        return $created;
    }

    public function markPaid(AffiliatePayout $payout, ?User $by, ?string $note = null): void
    {
        if ($payout->status !== 'pending') {
            return;
        }
        $payout->update(['status' => 'paid', 'paid_at' => now(), 'paid_by' => $by?->id, 'note' => $note ?: $payout->note]);
    }

    public function cancel(AffiliatePayout $payout, string $reason): void
    {
        if ($payout->status !== 'pending') {
            return;
        }
        $payout->update(['status' => 'cancelled', 'note' => mb_substr($reason, 0, 255)]);
    }

    public function minimum(): int
    {
        return max(0, (int) Setting::get('affiliate_min_payout', 100000));
    }
}
