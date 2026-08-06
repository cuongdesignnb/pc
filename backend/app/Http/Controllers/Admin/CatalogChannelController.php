<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\CatalogChannelException;
use App\Http\Controllers\Controller;
use App\Jobs\Catalog\BuildCatalogFeedCacheJob;
use App\Jobs\Catalog\SyncGoogleSheetsCatalogJob;
use App\Models\CatalogChannelConnection;
use App\Models\CatalogChannelEvent;
use App\Models\CatalogChannelSyncRun;
use App\Models\CatalogPriceBook;
use App\Services\Catalog\CatalogChannelAuditService;
use App\Services\Catalog\CatalogChannelManager;
use App\Services\Catalog\GoogleMerchant\GoogleMerchantFeedBuilder;
use App\Services\Catalog\GoogleSheets\GoogleSheetsConnectionTestService;
use App\Services\Catalog\GoogleSheets\GoogleSheetsExporter;
use App\Services\Catalog\Meta\MetaCatalogConnectionTestService;
use App\Services\Catalog\Meta\MetaCatalogFeedBuilder;
use App\Services\Catalog\Pricing\CatalogChannelPriceSettingsService;
use App\Services\Integrations\Kiot\KiotPriceBookSyncService;
use App\Services\Integrations\Kiot\KiotProductPriceSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use JsonException;
use Throwable;

class CatalogChannelController extends Controller
{
    public function __construct(
        private readonly CatalogChannelManager $channels,
        private readonly CatalogChannelAuditService $audit,
    ) {}

    public function index(): Response
    {
        $connections = collect(CatalogChannelConnection::CHANNELS)
            ->map(fn (string $channel) => $this->safeConnection($this->channels->connection($channel)))
            ->values();

        return Inertia::render('Admin/Integrations/CatalogChannels', [
            'connections' => $connections,
            'recentRuns' => CatalogChannelSyncRun::latest()->limit(25)->get(),
            'recentEvents' => CatalogChannelEvent::with('connection:id,channel')->latest()->limit(25)->get(),
            'priceBooks' => CatalogPriceBook::query()->withCount(['prices', 'prices as positive_prices_count' => fn ($query) => $query->where('price', '>', 0), 'prices as zero_prices_count' => fn ($query) => $query->where('price', 0)])->orderBy('name')->get(),
            'priceSettings' => app(CatalogChannelPriceSettingsService::class)->all(),
        ]);
    }

    public function updatePriceSelection(Request $request, string $channel, CatalogChannelPriceSettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'price_source' => ['required', 'string', 'max:64'],
            'fallback_policy' => ['required', 'in:none,retail_price,selected_price'],
        ]);
        $settings->update($channel, $validated['price_source'], $validated['fallback_policy'], $request->user()->id);

        return back()->with('success', 'Đã lưu nguồn giá cho channel.');
    }

    public function syncPriceBooks(Request $request, KiotPriceBookSyncService $service): RedirectResponse
    {
        session()->flash('catalog_result', $service->sync(false));

        return back()->with('success', 'Đã đồng bộ danh sách bảng giá KIOT.');
    }

    public function syncProductPrices(Request $request, KiotProductPriceSyncService $service): RedirectResponse
    {
        session()->flash('catalog_result', $service->sync(false));

        return back()->with('success', 'Đã đồng bộ giá sản phẩm theo bảng giá.');
    }

    public function updateGoogleSheets(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'spreadsheet_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{10,200}$/'],
            'worksheet' => ['required', 'string', 'max:100', 'not_regex:/[\\x00-\\x1F]/'],
            'service_account_json' => ['nullable', 'string', 'max:200000'],
            'is_enabled' => ['required', 'boolean'],
        ]);
        $connection = $this->channels->connection(CatalogChannelConnection::GOOGLE_SHEETS);
        $configuration = (array) $connection->configuration_encrypted;
        if (filled($validated['service_account_json'] ?? null)) {
            $configuration['service_account'] = $this->parseServiceAccount($validated['service_account_json']);
        }
        $configuration['spreadsheet_id'] = $validated['spreadsheet_id'];
        $configuration['worksheet'] = $validated['worksheet'];
        if ($validated['is_enabled'] && empty($configuration['service_account'])) {
            throw ValidationException::withMessages([
                'service_account_json' => 'Phải cấu hình Service Account trước khi bật channel.',
            ]);
        }

        $wasEnabled = $connection->is_enabled;
        $connection->update([
            'status' => empty($configuration['service_account']) ? 'not_configured' : 'configured',
            'is_enabled' => $validated['is_enabled'],
            'configuration_encrypted' => $configuration,
            'updated_by' => $request->user()->id,
            'created_by' => $connection->created_by ?: $request->user()->id,
            'last_error_at' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ]);
        $this->audit->record($connection, 'CONFIGURATION_UPDATED', $request->user(), [
            'spreadsheet_changed' => true,
            'credentials_replaced' => filled($validated['service_account_json'] ?? null),
        ], $request);
        $this->auditFlagChange($connection, $wasEnabled, $request);

        return back()->with('success', 'Đã lưu cấu hình Google Sheets an toàn.');
    }

    public function updateFlags(Request $request, string $channel): RedirectResponse
    {
        $this->assertCommerceChannel($channel);
        $validated = $request->validate(['is_enabled' => ['required', 'boolean']]);
        $connection = $this->channels->connection($channel);
        if ($validated['is_enabled'] && ! $this->channels->feedTokenConfigured($connection)) {
            throw ValidationException::withMessages(['channel' => 'Phải rotate feed token trước khi bật channel.']);
        }
        $wasEnabled = $connection->is_enabled;
        $connection->update([
            'is_enabled' => $validated['is_enabled'],
            'status' => $validated['is_enabled'] ? 'configured' : $connection->status,
            'updated_by' => $request->user()->id,
            'created_by' => $connection->created_by ?: $request->user()->id,
        ]);
        $this->auditFlagChange($connection, $wasEnabled, $request);

        return back()->with('success', 'Đã cập nhật trạng thái catalog channel.');
    }

    public function testGoogleSheets(Request $request, GoogleSheetsConnectionTestService $tester): RedirectResponse
    {
        return $this->perform($request, CatalogChannelConnection::GOOGLE_SHEETS, 'CONNECTION_TESTED', function () use ($tester) {
            $tester->test();

            return 'Kết nối Google Sheets thành công.';
        });
    }

    public function dryRunGoogleSheets(Request $request, GoogleSheetsExporter $exporter): RedirectResponse
    {
        return $this->perform($request, CatalogChannelConnection::GOOGLE_SHEETS, 'DRY_RUN_STARTED', function () use ($request, $exporter) {
            $result = $exporter->dryRun($request->user()->id);
            session()->flash('catalog_result', $result);

            return 'Google Sheets dry-run hoàn tất; không có dữ liệu remote nào bị ghi.';
        });
    }

    public function syncGoogleSheets(Request $request): RedirectResponse
    {
        $connection = $this->channels->connection(CatalogChannelConnection::GOOGLE_SHEETS);
        if (! $connection->is_enabled) {
            throw ValidationException::withMessages(['catalog' => 'Google Sheets channel đang tắt.']);
        }
        SyncGoogleSheetsCatalogJob::dispatch($request->user()->id);
        $this->audit->record($connection, 'SYNC_STARTED', $request->user(), ['queued' => true], $request);

        return back()->with('success', 'Đã đưa Google Sheets sync vào queue.');
    }

    public function validateFeed(Request $request, string $channel): RedirectResponse
    {
        $this->assertCommerceChannel($channel);

        return $this->perform($request, $channel, 'CONNECTION_TESTED', function () use ($channel) {
            $result = $channel === CatalogChannelConnection::GOOGLE_MERCHANT
                ? app(GoogleMerchantFeedBuilder::class)->validate()
                : app(MetaCatalogFeedBuilder::class)->validate();
            session()->flash('catalog_result', $result);

            return 'Feed hợp lệ.';
        });
    }

    public function testMetaConnection(Request $request, MetaCatalogConnectionTestService $tester): RedirectResponse
    {
        return $this->perform($request, CatalogChannelConnection::META_CATALOG, 'CONNECTION_TESTED', function () use ($tester) {
            session()->flash('catalog_result', $tester->test());

            return 'Meta Catalog test mode sẵn sàng; chưa gửi dữ liệu remote.';
        });
    }

    public function rebuildFeed(Request $request, string $channel): RedirectResponse
    {
        $this->assertCommerceChannel($channel);
        $connection = $this->channels->connection($channel);
        if (! $connection->is_enabled) {
            throw ValidationException::withMessages(['catalog' => 'Catalog channel đang tắt.']);
        }
        BuildCatalogFeedCacheJob::dispatch($channel, $request->user()->id);
        $this->audit->record($connection, 'SYNC_STARTED', $request->user(), ['queued' => true], $request);

        return back()->with('success', 'Đã đưa feed rebuild vào queue.');
    }

    public function rotateToken(Request $request, string $channel): RedirectResponse
    {
        $this->assertCommerceChannel($channel);
        $connection = $this->channels->connection($channel);
        $token = $this->channels->rotateFeedToken($connection);
        $connection->update([
            'updated_by' => $request->user()->id,
            'created_by' => $connection->created_by ?: $request->user()->id,
        ]);
        $this->audit->record($connection, 'TOKEN_ROTATED', $request->user(), [], $request);
        $route = $channel === CatalogChannelConnection::GOOGLE_MERCHANT
            ? 'feeds.google.products'
            : 'feeds.meta.products';

        return back()
            ->with('success', 'Feed token đã được rotate. URL chỉ hiển thị trong phản hồi này.')
            ->with('feed_url', route($route, ['token' => $token]));
    }

    private function safeConnection(CatalogChannelConnection $connection): array
    {
        $configuration = (array) $connection->configuration_encrypted;
        $lastRun = CatalogChannelSyncRun::where('channel', $connection->channel)->latest()->first();

        return [
            'channel' => $connection->channel,
            'status' => $connection->status,
            'is_enabled' => $connection->is_enabled,
            'spreadsheet_id' => $connection->channel === CatalogChannelConnection::GOOGLE_SHEETS
                ? ($configuration['spreadsheet_id'] ?? '')
                : null,
            'worksheet' => $connection->channel === CatalogChannelConnection::GOOGLE_SHEETS
                ? ($configuration['worksheet'] ?? 'Products')
                : null,
            'service_account_configured' => ! empty($configuration['service_account']),
            'feed_token_configured' => $this->channels->feedTokenConfigured($connection),
            'feed_path' => match ($connection->channel) {
                CatalogChannelConnection::GOOGLE_MERCHANT => route('feeds.google.products'),
                CatalogChannelConnection::META_CATALOG => route('feeds.meta.products'),
                default => null,
            },
            'last_tested_at' => $connection->last_tested_at,
            'last_success_at' => $connection->last_success_at,
            'last_error_at' => $connection->last_error_at,
            'last_error_code' => $connection->last_error_code,
            'last_error_message' => $connection->last_error_message,
            'last_run' => $lastRun,
            'price_setting' => app(CatalogChannelPriceSettingsService::class)->all()[$connection->channel] ?? null,
        ];
    }

    private function parseServiceAccount(string $json): array
    {
        try {
            $credentials = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['service_account_json' => 'Service Account JSON không hợp lệ.']);
        }

        $validator = Validator::make($credentials, [
            'type' => ['required', 'in:service_account'],
            'client_email' => ['required', 'email', 'max:320'],
            'private_key' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! str_starts_with($value, '-----BEGIN '.'PRIVATE KEY-----')) {
                    $fail('Service Account private key không hợp lệ.');
                }
            }],
            'token_uri' => ['nullable', 'in:https://oauth2.googleapis.com/token'],
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'service_account_json' => 'Service Account JSON thiếu trường bắt buộc hoặc token URI không an toàn.',
            ]);
        }

        return $credentials;
    }

    private function perform(Request $request, string $channel, string $event, callable $operation): RedirectResponse
    {
        $connection = $this->channels->connection($channel);
        try {
            $message = $operation();
            $this->audit->record($connection->fresh(), $event, $request->user(), ['success' => true], $request);

            return back()->with('success', $message);
        } catch (Throwable $exception) {
            $code = $exception instanceof CatalogChannelException ? $exception->errorCode : 'CHANNEL_OPERATION_FAILED';
            $this->audit->record($connection->fresh(), $event, $request->user(), [
                'success' => false,
                'error_code' => $code,
            ], $request);

            return back()->withErrors(['catalog' => $this->safeErrorMessage($code)]);
        }
    }

    private function safeErrorMessage(string $code): string
    {
        return match ($code) {
            'GOOGLE_CREDENTIALS_MISSING' => 'Chưa cấu hình Google Service Account.',
            'GOOGLE_AUTH_FAILED' => 'Google từ chối thông tin xác thực.',
            'GOOGLE_SHEET_NOT_FOUND' => 'Không tìm thấy Google Spreadsheet.',
            'GOOGLE_WORKSHEET_NOT_FOUND' => 'Không tìm thấy worksheet.',
            'GOOGLE_RATE_LIMITED' => 'Google đang giới hạn request; hãy thử lại sau.',
            'FEED_EMPTY' => 'Feed chưa có artifact hoặc không có sản phẩm hợp lệ.',
            default => 'Catalog channel không hoàn thành yêu cầu.',
        };
    }

    private function assertCommerceChannel(string $channel): void
    {
        abort_unless(in_array($channel, [
            CatalogChannelConnection::GOOGLE_MERCHANT,
            CatalogChannelConnection::META_CATALOG,
        ], true), 404);
    }

    private function auditFlagChange(CatalogChannelConnection $connection, bool $wasEnabled, Request $request): void
    {
        if ($wasEnabled !== $connection->is_enabled) {
            $this->audit->record(
                $connection,
                $connection->is_enabled ? 'CHANNEL_ENABLED' : 'CHANNEL_DISABLED',
                $request->user(),
                [],
                $request,
            );
        }
    }
}
