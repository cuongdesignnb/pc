<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('provider', 'kiot')
            ->where(function ($query): void {
                $query->where('kiot_availability_status', 'repairing')
                    ->orWhere('kiot_is_under_repair', true);
            })
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $product): void {
                $availableQuantity = max(
                    (int) ($product->kiot_available_quantity ?? 0),
                    max(0, (int) ($product->kiot_physical_quantity ?? 0) - (int) ($product->kiot_reserved_quantity ?? 0)),
                );
                $sellable = (bool) $product->is_active
                    && (bool) $product->show_on_pc_website
                    && $product->kiot_sync_status === 'active'
                    && $availableQuantity > 0
                    && (int) $product->price > 0;

                DB::table('products')->where('id', $product->id)->update([
                    'stock_quantity' => $sellable ? $availableQuantity : 0,
                    'kiot_available_quantity' => $availableQuantity,
                    'kiot_availability_status' => 'available',
                    'kiot_is_under_repair' => false,
                    'kiot_sellable' => $sellable,
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Original KIOT repair metadata is intentionally not restored.
    }
};
