<?php

namespace App\Console\Commands;

use App\Services\Affiliate\CommissionService;
use Illuminate\Console\Command;

class AffiliateApproveCommissionsCommand extends Command
{
    protected $signature = 'affiliate:approve-commissions';

    protected $description = 'Move CTV commissions whose approval window has passed to "Được rút"';

    public function handle(CommissionService $commissions): int
    {
        $this->info('Approved '.$commissions->approveDue().' commission(s).');

        return self::SUCCESS;
    }
}
