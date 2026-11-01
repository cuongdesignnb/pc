<?php

namespace App\Services\Catalog\Meta;

use App\Models\CatalogChannelConnection;
use App\Services\Catalog\Feeds\CatalogCommerceFeedBuilder;
use Illuminate\Support\Facades\Storage;

class MetaCatalogFeedBuilder
{
    public function __construct(
        private readonly CatalogCommerceFeedBuilder $builder,
        private readonly MetaCatalogCsvRenderer $renderer,
        private readonly MetaCatalogFeedValidator $validator,
        private readonly MetaCatalogItemStrategy $items,
        private readonly MetaCatalogSubsetWriter $subsets,
    ) {}

    public function build(bool $dryRun = false, ?int $requestedBy = null): array
    {
        $result = $this->builder->build(
            CatalogChannelConnection::META_CATALOG,
            (string) config('catalog.meta_catalog.artifact'),
            $this->renderer,
            $this->validator,
            $dryRun,
            $requestedBy,
            $this->items,
        );
        if (! $dryRun && ($result['artifact'] ?? null)) {
            $this->subsets->write(Storage::disk((string) config('catalog.feed_disk', 'local'))->path($result['artifact']));
        }

        return $result;
    }

    public function validate(): array
    {
        return $this->builder->validateExisting(
            (string) config('catalog.meta_catalog.artifact'),
            $this->validator,
        );
    }
}
