<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Integrations\Kiot\ProcessKiotOutboxEvent;
use App\Jobs\Integrations\Kiot\SyncKiotProducts;
use App\Models\IntegrationOutboxEvent;
use App\Models\IntegrationSyncState;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class KiotIntegrationController extends Controller
{
    public function index(): Response
    {
        $baseUrl = (string) config('integrations.kiot.base_url');
        $parsed = $baseUrl ? parse_url($baseUrl) : [];
        $maskedUrl = $parsed ? (($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '***').(isset($parsed['port']) ? ':'.$parsed['port'] : '')) : null;

        return Inertia::render('Admin/Integrations/Kiot', [
            'configuration' => [
                'enabled' => (bool) config('integrations.kiot.enabled'),
                'product_sync_enabled' => (bool) config('integrations.kiot.product_sync_enabled'),
                'order_sync_enabled' => (bool) config('integrations.kiot.order_sync_enabled'),
                'base_url' => $maskedUrl,
                'client_id' => config('integrations.kiot.client_id'),
                'configured' => (bool) ($baseUrl && config('integrations.kiot.client_id') && config('integrations.kiot.secret')),
            ],
            'syncState' => IntegrationSyncState::where(['integration' => 'kiot', 'resource' => 'products'])->first(),
            'counts' => [
                'product_errors' => Product::whereNotNull('kiot_sync_error_code')->count(),
                'products_stale' => Product::where('inventory_source', 'kiot')
                    ->where(function ($query) {
                        $query->whereNull('kiot_synced_at')
                            ->orWhere('kiot_synced_at', '<', now()->subMinutes((int) config('integrations.kiot.product_stale_after_minutes')));
                    })->count(),
                'orders_pending' => Order::whereIn('kiot_sync_status', ['pending', 'sending'])->count(),
                'orders_retrying' => Order::where('kiot_sync_status', 'retrying')->count(),
                'orders_rejected' => Order::where('kiot_sync_status', 'rejected')->count(),
                'dead_letter' => IntegrationOutboxEvent::where(['integration' => 'kiot', 'status' => 'dead_letter'])->count(),
            ],
            'recentErrors' => IntegrationOutboxEvent::where('integration', 'kiot')
                ->whereNotNull('last_error_code')->latest('last_attempt_at')->limit(20)
                ->get(['id', 'event_type', 'aggregate_id', 'status', 'attempt_count', 'last_error_code', 'last_error_message', 'last_attempt_at']),
        ]);
    }

    public function dryRun(): RedirectResponse
    {
        SyncKiotProducts::dispatch(full: true, dryRun: true);

        return back()->with('success', 'Đã đưa product dry-run vào hàng đợi.');
    }

    public function sync(): RedirectResponse
    {
        SyncKiotProducts::dispatch(full: true);

        return back()->with('success', 'Đã đưa product sync vào hàng đợi.');
    }

    public function retry(): RedirectResponse
    {
        IntegrationOutboxEvent::where('integration', 'kiot')->whereIn('status', ['retrying', 'dead_letter'])
            ->select(['id', 'status'])->chunkById(100, function ($events) {
                foreach ($events as $event) {
                    if ($event->status === 'dead_letter') {
                        $event->update(['status' => 'retrying', 'attempt_count' => 0, 'next_attempt_at' => now()]);
                    }
                    ProcessKiotOutboxEvent::dispatch($event->id);
                }
            });

        return back()->with('success', 'Đã đưa các đơn lỗi vào hàng đợi retry.');
    }

    public function retryEvent(IntegrationOutboxEvent $event): RedirectResponse
    {
        abort_unless($event->integration === 'kiot', 404);
        if (in_array($event->status, ['dead_letter', 'retrying'], true)) {
            $event->update(['status' => 'retrying', 'attempt_count' => 0, 'next_attempt_at' => now(), 'locked_at' => null]);
            ProcessKiotOutboxEvent::dispatch($event->id);
        }

        return back()->with('success', 'Đã đưa sự kiện vào hàng đợi retry.');
    }
}
