<?php

namespace App\Services\Catalog;

use App\Exceptions\CatalogChannelException;
use App\Models\CatalogChannelConnection;
use App\Models\CatalogChannelSyncRun;
use App\Models\CatalogChannelSyncRunItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CatalogBulkSyncService
{
    public function __construct(
        private readonly CatalogSelectionPreviewService $preview,
        private readonly CatalogChannelAuditService $audit,
        private readonly CatalogChannelManager $channels,
    ) {}

    public function sync(array $payload, ?User $actor = null, ?Request $request = null): array
    {
        if (($payload['confirmed'] ?? false) !== true) {
            throw ValidationException::withMessages(['confirmed' => 'Phải xác nhận preview trước khi thực hiện bulk action.']);
        }
        $result = $this->preview->preview($payload);
        $summary = $result['summary'];
        $channel = $summary['CHANNEL'];
        if ($channel !== CatalogChannelConnection::GOOGLE_SHEETS && (int) $summary['ELIGIBLE_COUNT'] === 0) {
            throw ValidationException::withMessages(['eligible_count' => 'Không có sản phẩm hợp lệ để đồng bộ.']);
        }

        $run = CatalogChannelSyncRun::create([
            'channel' => $channel,
            'mode' => 'bulk_sync',
            'status' => 'accepted',
            'started_at' => now(),
            'completed_at' => now(),
            'items_total' => $summary['SELECTED_COUNT'],
            'items_valid' => $summary['VALID_ROWS'],
            'items_invalid' => $summary['INVALID_ROWS'],
            'items_created' => $summary['CREATE_COUNT'],
            'items_updated' => $summary['UPDATE_COUNT'],
            'items_skipped' => $summary['SKIPPED_COUNT'],
            'errors' => $summary['INVALID_COUNT'],
            'summary_json' => $summary + ['preview_token' => $result['preview_token']],
            'requested_by' => $actor?->id,
        ]);
        $items = array_map(fn (array $item): array => [
            'sync_run_id' => $run->id,
            'product_id' => $item['id'],
            'external_id' => $item['external_id'],
            'action' => $item['action'],
            'eligibility_status' => $item['eligible'] ? 'eligible' : 'invalid',
            'validation_errors_json' => json_encode($item['validation_errors'], JSON_THROW_ON_ERROR),
            'selected_price' => $item['selected_price'],
            'price_source' => $item['price_source'],
            'image_status' => $item['image_status'],
            'result_status' => 'preview_only',
            'error_code' => $item['validation_errors'][0] ?? null,
            'error_message' => empty($item['validation_errors']) ? null : implode('|', $item['validation_errors']),
            'created_at' => now(),
            'updated_at' => now(),
        ], $result['items']);
        if ($items !== []) {
            CatalogChannelSyncRunItem::upsert($items, ['sync_run_id', 'product_id'], [
                'external_id', 'action', 'eligibility_status', 'validation_errors_json', 'selected_price',
                'price_source', 'image_status', 'result_status', 'error_code', 'error_message', 'updated_at',
            ]);
        }
        $connection = $this->channels->connection($channel);
        $this->audit->record($connection, 'CATALOG_BULK_SYNC_REQUESTED', $actor, [
            'channel' => $channel,
            'selection_mode' => $summary['SELECTION_SCOPE'],
            'filter_summary' => $summary['filter_snapshot'],
            'selected_count' => $summary['SELECTED_COUNT'],
            'eligible_count' => $summary['ELIGIBLE_COUNT'],
            'invalid_count' => $summary['INVALID_COUNT'],
            'price_source' => $summary['PRICE_SOURCE'],
            'remote_submitted' => false,
        ], $request);

        return $result + ['run_id' => $run->id, 'remote_submitted' => false];
    }

    public function override(array $payload, string $action, ?User $actor = null, ?Request $request = null): array
    {
        $result = $this->preview->preview($payload);
        $summary = $result['summary'];
        $connection = $this->channels->connection($summary['CHANNEL']);
        $event = match ($action) {
            'enable' => 'CATALOG_BULK_CHANNEL_ENABLED',
            'disable' => 'CATALOG_BULK_CHANNEL_DISABLED',
            default => 'CATALOG_BULK_CHANNEL_OVERRIDE_RESET',
        };
        $this->audit->record($connection, $event, $actor, [
            'channel' => $summary['CHANNEL'],
            'selection_mode' => $summary['SELECTION_SCOPE'],
            'filter_summary' => $summary['filter_snapshot'],
            'selected_count' => $summary['SELECTED_COUNT'],
            'eligible_count' => $summary['ELIGIBLE_COUNT'],
            'invalid_count' => $summary['INVALID_COUNT'],
            'price_source' => $summary['PRICE_SOURCE'],
            'action' => $action,
        ], $request);

        return $result + ['override_action' => $action, 'remote_submitted' => false];
    }

    public function assertChannel(string $channel): void
    {
        if (! in_array($channel, [CatalogChannelConnection::GOOGLE_SHEETS, CatalogChannelConnection::GOOGLE_MERCHANT, CatalogChannelConnection::META_CATALOG], true)) {
            throw new CatalogChannelException('CHANNEL_UNSUPPORTED', 'Catalog channel không hỗ trợ bulk action.', 422);
        }
    }
}
