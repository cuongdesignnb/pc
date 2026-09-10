<?php

namespace App\Console\Commands;

use App\Services\Seo\SlugMigrationPlanner;
use Illuminate\Console\Command;

class SeoSlugAuditCommand extends Command
{
    protected $signature = 'seo:slug-audit {--json : Print the complete read-only plan as JSON}';

    protected $description = 'Audit public slugs, reserved route conflicts and deterministic migration candidates.';

    public function handle(SlugMigrationPlanner $planner): int
    {
        $manifest = $planner->plan('audit-'.now()->format('YmdHis'));
        if ($this->option('json')) {
            $this->line(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->table(
            ['Decision', 'Count'],
            collect($manifest['counts'])->map(fn (int $count, string $decision): array => [$decision, $count])->values()->all(),
        );
        $this->line('CHECKSUM='.$manifest['checksum']);
        $this->line('REVIEW_REQUIRED='.(($manifest['counts']['REVIEW'] ?? 0) + ($manifest['counts']['COLLISION'] ?? 0)));

        return self::SUCCESS;
    }
}
