<?php

namespace App\Console\Commands;

use App\Jobs\Catalog\SyncGoogleSheetsCatalogJob;
use Illuminate\Console\Command;

class GoogleSheetsCatalogSyncCommand extends Command
{
    protected $signature = 'catalog:google-sheets:sync {--now : Run synchronously}';

    protected $description = 'Synchronize the catalog to Google Sheets';

    public function handle(): int
    {
        $this->option('now')
            ? SyncGoogleSheetsCatalogJob::dispatchSync()
            : SyncGoogleSheetsCatalogJob::dispatch();
        $this->info('Google Sheets catalog synchronization queued.');

        return self::SUCCESS;
    }
}
