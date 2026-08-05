<?php

namespace App\Console\Commands;

use App\Services\Catalog\Meta\MetaCatalogFeedBuilder;
use Illuminate\Console\Command;

class MetaCatalogBuildCommand extends Command
{
    protected $signature = 'catalog:meta:build {--dry-run}';

    protected $description = 'Build the Meta catalog preview without remote submission';

    public function handle(MetaCatalogFeedBuilder $builder): int
    {
        $this->line(json_encode($builder->build((bool) $this->option('dry-run')), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
