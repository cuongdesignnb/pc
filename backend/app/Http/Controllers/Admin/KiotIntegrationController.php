<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\KiotIntegrationException;
use App\Http\Controllers\Controller;
use App\Jobs\Integrations\Kiot\ProcessKiotOutboxEvent;
use App\Jobs\Integrations\Kiot\SyncKiotProducts;
use App\Jobs\Integrations\Kiot\SyncKiotProductsBySku;
use App\Models\IntegrationConnection;
use App\Models\IntegrationConnectionEvent;
use App\Models\IntegrationOutboxEvent;
use App\Models\IntegrationSyncConflict;
use App\Models\IntegrationSyncRun;
use App\Models\IntegrationSyncState;
use App\Models\Order;
use App\Models\Product;
use App\Services\Integrations\Kiot\KiotClient;
use App\Services\Integrations\Kiot\KiotConfigurationAuditService;
use App\Services\Integrations\Kiot\KiotConfigurationResolver;
use App\Services\Integrations\Kiot\KiotConnectionManagementService;
use App\Services\Integrations\Kiot\KiotConnectionTestService;
use App\Services\Integrations\Kiot\KiotPairingService;
use App\Services\Integrations\Kiot\KiotProductSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KiotIntegrationController extends Controller
{
    public function __construct(
        private readonly KiotConfigurationResolver $resolver,
        private readonly KiotConnectionManagementService $management,
        private readonly KiotConnectionTestService $connectionTest,
        private readonly KiotPairingService $pairing,
        private readonly KiotConfigurationAuditService $audit,
        private readonly KiotClient $client,
    ) {}

    public function index(Request $request): Response
    {
        $runtime = $this->resolver->resolve();
        $connection = $runtime->databaseConnectionId
            ? IntegrationConnection::find($runtime->databaseConnectionId)
            : null;
        $environment = $this->resolver->environmentBootstrap();
        $databaseHistory = $this->resolver->hasDatabaseConfigurationHistory();
        $latestRun = IntegrationSyncRun::query()
            ->where(['provider' => 'kiot', 'resource' => 'products'])
            ->latest('id')
            ->first();

        return Inertia::render('Admin/Integrations/Kiot', [
            'configuration' => [
                'source' => $runtime->source,
                'database_history' => $databaseHistory,
                'environment_import_available' => ! $databaseHistory && $environment->configured,
                'enabled' => $runtime->enabled,
                'product_sync_enabled' => $runtime->productSyncEnabled,
                'order_sync_enabled' => $runtime->orderSyncEnabled,
                'base_url' => $runtime->baseUrl,
                'client_id' => $runtime->clientId,
                'configured' => $runtime->configured,
                'connected' => $runtime->connected,
                'connection_status' => $connection?->connection_status ?? ($runtime->connected ? 'connected' : 'unconfigured'),
                'secret_fingerprint' => $connection?->secret_fingerprint,
                'api_version' => $runtime->apiVersion,
                'capabilities' => $runtime->capabilities,
                'last_tested_at' => $connection?->last_tested_at,
                'last_connected_at' => $connection?->last_connected_at,
                'last_error_at' => $connection?->last_error_at,
                'last_error_code' => $connection?->last_error_code,
                'last_error_message' => $connection?->last_error_message,
                'website_url' => rtrim((string) config('app.url'), '/'),
            ],
            'syncState' => IntegrationSyncState::where(['integration' => 'kiot', 'resource' => 'products'])->first(),
            'syncRuns' => IntegrationSyncRun::query()
                ->where(['provider' => 'kiot', 'resource' => 'products'])
                ->with('requester:id,name,email')
                ->latest('id')
                ->limit(25)
                ->get(),
            'syncConflicts' => IntegrationSyncConflict::query()
                ->where(['provider' => 'kiot', 'resource' => 'products', 'status' => 'open'])
                ->with('product:id,name,sku')
                ->latest('id')
                ->limit(25)
                ->get(),
            'selectedPriceBook' => data_get($latestRun?->totals_json, 'selected_price_book'),
            'nextScheduledRun' => $runtime->productSyncEnabled ? now()->addMinutes(5)->toIso8601String() : null,
            'counts' => [
                'product_errors' => Product::whereNotNull('kiot_sync_error_code')->count(),
                'products_stale' => Product::where('inventory_source', 'kiot')
                    ->where(function ($query) use ($runtime) {
                        $query->whereNull('kiot_synced_at')
                            ->orWhere('kiot_synced_at', '<', now()->subMinutes($runtime->productStaleAfterMinutes));
                    })->count(),
                'orders_pending' => Order::whereIn('kiot_sync_status', ['pending', 'sending'])->count(),
                'orders_retrying' => Order::where('kiot_sync_status', 'retrying')->count(),
                'orders_rejected' => Order::where('kiot_sync_status', 'rejected')->count(),
                'dead_letter' => IntegrationOutboxEvent::where(['integration' => 'kiot', 'status' => 'dead_letter'])->count(),
            ],
            'recentErrors' => IntegrationOutboxEvent::where('integration', 'kiot')
                ->whereNotNull('last_error_code')->latest('last_attempt_at')->limit(20)
                ->get(['id', 'event_type', 'aggregate_id', 'status', 'attempt_count', 'last_error_code', 'last_error_message', 'last_attempt_at']),
            'history' => IntegrationConnectionEvent::query()
                ->where('provider', IntegrationConnection::PROVIDER_KIOT)
                ->with('actor:id,name,email')
                ->latest('created_at')
                ->limit(50)
                ->get(['id', 'integration_connection_id', 'event', 'actor_id', 'metadata', 'created_at']),
            'skuPreview' => $request->session()->get('kiot_preview'),
        ]);
    }

    public function pair(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'base_url' => ['required', 'string', 'max:2048'],
            'reference' => ['required', 'string', 'max:255'],
            'pairing_code' => ['required', 'string', 'max:512'],
            'replace_existing_credentials' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $this->pairing->pair($data, $request->user(), $request);
        } catch (KiotIntegrationException $exception) {
            return $this->domainError($exception);
        }

        return $result['test']['success']
            ? back()->with('success', 'Ghép nối và kiểm tra kết nối KIOT thành công. Các cờ vẫn đang tắt.')
            : back()->withErrors(['kiot' => "{$result['test']['error_code']}: {$result['test']['message']}"]);
    }

    public function manual(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'base_url' => ['required', 'string', 'max:2048'],
            'client_id' => ['required', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:4096'],
            'api_version' => ['nullable', 'string', 'max:32'],
        ]);

        return $this->runAction(function () use ($data, $request) {
            $this->management->saveManual($data, $request->user(), $request);

            return 'Đã lưu cấu hình thủ công. Hãy kiểm tra kết nối trước khi bật tích hợp.';
        });
    }

    public function importEnvironment(Request $request): RedirectResponse
    {
        return $this->runAction(function () use ($request) {
            $this->management->importEnvironment($request->user(), $request);

            return 'Đã nhập cấu hình môi trường. Tất cả cờ vẫn đang tắt.';
        });
    }

    public function testConnection(Request $request): RedirectResponse
    {
        $result = $this->connectionTest->test($request->user(), $request);

        return $result['success']
            ? back()->with('success', 'Kết nối KIOT thành công.')
            : back()->withErrors(['kiot' => "{$result['error_code']}: {$result['message']}"]);
    }

    public function updateFlags(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'product_sync_enabled' => ['required', 'boolean'],
            'order_sync_enabled' => ['required', 'boolean'],
            'confirm_order_sync' => ['sometimes', 'boolean'],
        ]);

        return $this->runAction(function () use ($data, $request) {
            $this->management->updateFlags(
                $data,
                (bool) ($data['confirm_order_sync'] ?? false),
                $request->user(),
                $request,
            );

            return 'Đã cập nhật các cờ tích hợp KIOT.';
        });
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->validate(['confirm_disconnect' => ['accepted']]);

        return $this->runAction(function () use ($request) {
            $this->management->disconnect($request->user(), $request);

            return 'Đã ngắt kết nối KIOT và xoá credential đã mã hoá.';
        });
    }

    public function dryRun(Request $request): RedirectResponse
    {
        try {
            $runtime = $this->client->assertConnected();
        } catch (KiotIntegrationException $exception) {
            return $this->domainError($exception);
        }
        $run = $this->createRun('dry-run', $request);
        SyncKiotProducts::dispatch(
            full: true,
            dryRun: true,
            runId: $run->id,
            requestedBy: $request->user()?->id,
        );
        $this->recordOperation($runtime->databaseConnectionId, 'product.dry_run_requested', $request);

        return back()->with('success', "Đã đưa product dry-run #{$run->id} vào hàng đợi.");
    }

    public function testSku(Request $request, KiotProductSyncService $service): RedirectResponse
    {
        $data = $request->validate(['sku' => ['required', 'string', 'max:255', 'regex:/^[^\x00-\x1F\x7F]+$/']]);

        try {
            $runtime = $this->client->assertConnected();
            $report = $service->sync(dryRun: true, sku: $data['sku'], requestedBy: $request->user()?->id);
            $this->recordOperation($runtime->databaseConnectionId, 'product.targeted_test_requested', $request, ['sku' => $data['sku']]);

            return back()->with('success', 'Đã kiểm tra SKU mà không ghi dữ liệu.')
                ->with('kiot_preview', $report['preview'][0] ?? ['sku' => $data['sku'], 'local_match' => false]);
        } catch (KiotIntegrationException $exception) {
            return $this->domainError($exception);
        }
    }

    public function syncOne(Request $request): RedirectResponse
    {
        $data = $request->validate(['sku' => ['required', 'string', 'max:255', 'regex:/^[^\x00-\x1F\x7F]+$/']]);

        try {
            $runtime = $this->client->assertProductSyncEnabled();
        } catch (KiotIntegrationException $exception) {
            return $this->domainError($exception);
        }
        SyncKiotProductsBySku::dispatch([$data['sku']], requestedBy: $request->user()?->id);
        $this->recordOperation($runtime->databaseConnectionId, 'product.targeted_sync_requested', $request, ['sku' => $data['sku']]);

        return back()->with('success', 'Đã đưa SKU vào hàng đợi đồng bộ.');
    }

    public function sync(Request $request): RedirectResponse
    {
        try {
            $runtime = $this->client->assertProductSyncEnabled();
        } catch (KiotIntegrationException $exception) {
            return $this->domainError($exception);
        }
        $run = $this->createRun('full', $request);
        SyncKiotProducts::dispatch(full: true, runId: $run->id, requestedBy: $request->user()?->id);
        $this->recordOperation($runtime->databaseConnectionId, 'product.full_sync_requested', $request);

        return back()->with('success', "Đã đưa full product sync #{$run->id} vào hàng đợi.");
    }

    public function incremental(Request $request): RedirectResponse
    {
        try {
            $runtime = $this->client->assertProductSyncEnabled();
        } catch (KiotIntegrationException $exception) {
            return $this->domainError($exception);
        }
        $run = $this->createRun('incremental', $request);
        SyncKiotProducts::dispatch(full: false, runId: $run->id, requestedBy: $request->user()?->id);
        $this->recordOperation($runtime->databaseConnectionId, 'product.incremental_sync_requested', $request);

        return back()->with('success', "Đã đưa incremental product sync #{$run->id} vào hàng đợi.");
    }

    public function showRun(IntegrationSyncRun $run): Response
    {
        abort_unless($run->provider === 'kiot' && $run->resource === 'products', 404);

        return Inertia::render('Admin/Integrations/KiotRun', [
            'run' => $run->load('requester:id,name,email'),
            'conflicts' => IntegrationSyncConflict::query()
                ->where(['provider' => 'kiot', 'resource' => 'products'])
                ->whereBetween('created_at', [$run->created_at, $run->completed_at ?? now()])
                ->with('product:id,name,sku')
                ->latest('id')
                ->get(),
        ]);
    }

    public function retry(Request $request): RedirectResponse
    {
        try {
            $runtime = $this->client->assertOrderSyncEnabled();
        } catch (KiotIntegrationException $exception) {
            return $this->domainError($exception);
        }
        IntegrationOutboxEvent::where('integration', 'kiot')->whereIn('status', ['retrying', 'dead_letter'])
            ->select(['id', 'status'])->chunkById(100, function ($events) {
                foreach ($events as $event) {
                    if ($event->status === 'dead_letter') {
                        $event->update(['status' => 'retrying', 'attempt_count' => 0, 'next_attempt_at' => now()]);
                    }
                    ProcessKiotOutboxEvent::dispatch($event->id);
                }
            });
        $this->recordOperation($runtime->databaseConnectionId, 'order.retry_requested', $request, ['scope' => 'all']);

        return back()->with('success', 'Đã đưa các đơn lỗi vào hàng đợi retry.');
    }

    public function retryEvent(Request $request, IntegrationOutboxEvent $event): RedirectResponse
    {
        abort_unless($event->integration === 'kiot', 404);
        try {
            $runtime = $this->client->assertOrderSyncEnabled();
        } catch (KiotIntegrationException $exception) {
            return $this->domainError($exception);
        }
        if (in_array($event->status, ['dead_letter', 'retrying'], true)) {
            $event->update(['status' => 'retrying', 'attempt_count' => 0, 'next_attempt_at' => now(), 'locked_at' => null]);
            ProcessKiotOutboxEvent::dispatch($event->id);
        }
        $this->recordOperation($runtime->databaseConnectionId, 'order.retry_requested', $request, ['event_id' => $event->id]);

        return back()->with('success', 'Đã đưa sự kiện vào hàng đợi retry.');
    }

    private function runAction(callable $action): RedirectResponse
    {
        try {
            return back()->with('success', $action());
        } catch (KiotIntegrationException $exception) {
            return $this->domainError($exception);
        }
    }

    private function domainError(KiotIntegrationException $exception): RedirectResponse
    {
        $safeCodes = [
            'INTEGRATION_NOT_CONFIGURED', 'INTEGRATION_NOT_CONNECTED', 'INTEGRATION_DISABLED',
            'PRODUCT_SYNC_DISABLED', 'ORDER_SYNC_DISABLED', 'INVALID_PROVIDER_URL',
            'INSECURE_PROVIDER_URL', 'PRIVATE_PROVIDER_URL', 'PROVIDER_HOST_UNRESOLVED',
            'UNSUPPORTED_API_VERSION', 'DATABASE_CONFIGURATION_EXISTS',
            'ENVIRONMENT_CONFIGURATION_INCOMPLETE', 'CREDENTIAL_REPLACEMENT_CONFIRMATION_REQUIRED',
            'ORDER_SYNC_CONFIRMATION_REQUIRED', 'CAPABILITY_NOT_SUPPORTED',
            'INVALID_PAIRING_TOKEN', 'PAIRING_ATTEMPTS_EXCEEDED', 'PAIRING_TOKEN_USED',
            'PAIRING_TOKEN_EXPIRED', 'PAIRING_ORIGIN_MISMATCH',
            'PAIRING_PROVIDER_ORIGIN_MISMATCH', 'INTEGRATION_REVOKED',
            'CONNECTION_ERROR', 'INVALID_RESPONSE',
        ];
        $message = in_array($exception->errorCode, $safeCodes, true)
            ? $exception->getMessage()
            : 'Không thể hoàn tất thao tác KIOT.';

        return back()->withErrors(['kiot' => "{$exception->errorCode}: {$message}"]);
    }

    private function recordOperation(?int $connectionId, string $event, Request $request, array $metadata = []): void
    {
        if (! $connectionId || ! $connection = IntegrationConnection::find($connectionId)) {
            return;
        }

        $this->audit->record($connection, $event, $request->user(), $metadata, $request);
    }

    private function createRun(string $mode, Request $request): IntegrationSyncRun
    {
        return IntegrationSyncRun::create([
            'provider' => 'kiot',
            'resource' => 'products',
            'mode' => $mode,
            'status' => 'queued',
            'requested_by' => $request->user()?->id,
        ]);
    }
}
