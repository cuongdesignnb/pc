<?php

namespace App\Console\Commands;

use App\Services\Affiliate\PayoutService;
use Illuminate\Console\Command;

class AffiliateCreatePayoutsCommand extends Command
{
    protected $signature = 'affiliate:create-payouts';

    protected $description = 'Create withdrawal requests for CTVs whose payout day is today';

    public function handle(PayoutService $payouts): int
    {
        $this->info('Created '.$payouts->createScheduled().' payout request(s).');

        return self::SUCCESS;
    }
}
