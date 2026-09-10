<?php

namespace App\Console\Commands;

use App\Services\Seo\SlugMigrationPlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;

class SeoSlugPlanCommand extends Command
{
    protected $signature = 'seo:slug-plan
        {--output= : JSON manifest path; defaults to storage/app/seo/slug-plan.json}
        {--batch= : Stable batch identifier for the manifest}';

    protected $description = 'Create a read-only, checksum-protected public slug migration plan.';

    public function handle(SlugMigrationPlanner $planner): int
    {
        $manifest = $planner->plan($this->option('batch'));
        $path = (string) ($this->option('output') ?: storage_path('app/seo/slug-plan.json'));
        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\/]/', $path)) {
            $path = base_path($path);
        }
        File::ensureDirectoryExists(dirname($path));

        try {
            File::put($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
        } catch (JsonException $exception) {
            $this->error('Không thể ghi manifest: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line('PLAN_FILE='.$path);
        $this->line('PLAN_CHECKSUM='.$manifest['checksum']);
        $this->line('PLAN_BATCH='.$manifest['batch_id']);
        foreach ($manifest['counts'] as $decision => $count) {
            $this->line(strtoupper($decision).'='.$count);
        }
        $this->warn('Read-only plan. Chưa có public slug nào bị thay đổi.');

        return self::SUCCESS;
    }
}
