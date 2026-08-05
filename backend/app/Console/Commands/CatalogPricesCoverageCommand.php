<?php

namespace App\Console\Commands;

use App\Models\CatalogPriceBook;
use App\Models\CatalogProductPrice;
use Illuminate\Console\Command;

class CatalogPricesCoverageCommand extends Command
{
    protected $signature = 'catalog:prices:coverage';

    protected $description = 'Report local catalog price-book coverage';

    public function handle(): int
    {
        $rows = CatalogPriceBook::query()->withCount(['prices', 'prices as positive_prices_count' => fn ($query) => $query->where('price', '>', 0), 'prices as zero_prices_count' => fn ($query) => $query->where('price', 0)])->get();
        $this->table(['ID', 'Name', 'Active', 'Rows', 'Positive', 'Zero'], $rows->map(fn ($book) => [$book->id, $book->name, $book->is_active ? 'yes' : 'no', $book->prices_count, $book->positive_prices_count, $book->zero_prices_count]));
        $this->line('PRODUCT_PRICE_ROWS='.CatalogProductPrice::query()->count());

        return self::SUCCESS;
    }
}
