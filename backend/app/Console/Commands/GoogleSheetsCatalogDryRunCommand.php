<?php

namespace App\Console\Commands;

use App\Services\Catalog\GoogleSheets\GoogleSheetsExporter;
use Illuminate\Console\Command;

class GoogleSheetsCatalogDryRunCommand extends Command
{
    protected $signature = 'catalog:google-sheets:dry-run';

    protected $description = 'Preview Google Sheets catalog changes without writing remote data';

    public function handle(GoogleSheetsExporter $exporter): int
    {
        $this->table(['Metric', 'Value'], collect($exporter->dryRun())->map(fn ($value, $key) => [
            $key,
            is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value,
        ]));

        return self::SUCCESS;
    }
}
