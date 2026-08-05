<?php

namespace App\Console\Commands;

use App\Services\Catalog\Meta\MetaCatalogFeedBuilder;
use Illuminate\Console\Command;

class MetaCatalogValidateCommand extends Command
{
    protected $signature = 'catalog:meta:validate';

    protected $description = 'Validate the current Meta catalog artifact';

    public function handle(MetaCatalogFeedBuilder $builder): int
    {
        $this->line(json_encode($builder->validate(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
