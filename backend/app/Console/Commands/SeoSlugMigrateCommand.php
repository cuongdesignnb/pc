<?php

namespace App\Console\Commands;

use App\Services\Seo\SlugMigrationPlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class SeoSlugMigrateCommand extends Command
{
    protected $signature = 'seo:slug-migrate
        {manifest : JSON manifest created by seo:slug-plan}
        {--checksum= : Exact PLAN_CHECKSUM from the approved manifest}
        {--approved-by= : Operator or change-ticket identifier}
        {--apply : Mutate slugs; without this flag the command only validates}';

    protected $description = 'Validate or explicitly apply an approved public slug migration manifest.';

    public function handle(SlugMigrationPlanner $planner): int
    {
        $path = (string) $this->argument('manifest');
        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\/]/', $path)) {
            $path = base_path($path);
        }
        if (! File::exists($path)) {
            $this->error('Không tìm thấy manifest: '.$path);

            return self::FAILURE;
        }

        try {
            $manifest = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($manifest)) {
                throw new InvalidArgumentException('Manifest phải là object JSON.');
            }
            $checksum = (string) ($manifest['checksum'] ?? '');
            if (! hash_equals($checksum, $planner->checksum($manifest))) {
                throw new InvalidArgumentException('Checksum trong file không khớp nội dung manifest.');
            }

            if (! $this->option('apply')) {
                $this->line('DRY_RUN=YES');
                $this->line('MANIFEST_CHECKSUM='.$checksum);
                $this->line('Không thay đổi database. Dùng --apply + --checksum + --approved-by khi đã review.');

                return self::SUCCESS;
            }

            $result = $planner->apply($manifest, (string) $this->option('checksum'), (string) $this->option('approved-by'));
            $this->line('APPLY=YES');
            $this->line('BATCH_ID='.$result['batch_id']);
            $this->line('CHANGED='.$result['changed']);
            $this->line('CHECKSUM='.$result['checksum']);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('SEO_SLUG_MIGRATION_FAILED='.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
