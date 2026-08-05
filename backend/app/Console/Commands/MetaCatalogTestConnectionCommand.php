<?php

namespace App\Console\Commands;

use App\Services\Catalog\Meta\MetaCatalogConnectionTestService;
use Illuminate\Console\Command;

class MetaCatalogTestConnectionCommand extends Command
{
    protected $signature = 'catalog:meta:test-connection';

    protected $description = 'Verify Meta catalog test-mode configuration without remote submission';

    public function handle(MetaCatalogConnectionTestService $service): int
    {
        $this->line(json_encode($service->test(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
