<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OrderWriteException;
use App\Http\Controllers\Controller;
use App\Models\AdminApiWriteLog;
use App\Models\Order;
use App\Services\AdminApi\AdminApiTokens;
use App\Services\Orders\OrderApiPresenter;
use App\Services\Orders\OrderHistoryRecorder;
use App\Services\Orders\OrderWriteService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * Order changes for "orders_write" admin API tokens (n8n, the shop's tools):
 * delivery status, payments received outside SePay, carrier and tracking
 * code. Every call is written to admin_api_write_logs and every change to the
 * order history as "Token: <name>". Payments require an Idempotency-Key.
 */
class AdminApiOrderWriteController extends Controller
{
    public function __construct(
        private readonly OrderWriteService $orders,
        private readonly OrderApiPresenter $presenter,
    ) {}

    public function status(Request $request, int $order): JsonResponse
    {
        return $this->run($request, 'order.status', $order, false, function (Order $order, string $actor) use ($request) {
            $data = Validator::make($request->all(), [
                'order_status' => ['required', Rule::in(OrderWriteService::API_STATUSES)],
                'reason' => ['nullable', 'string', 'max:500'],
            ])->validate();
            $reason = filled($data['reason'] ?? null) ? trim($data['reason']) : null;
            $message = $reason ?? ($data['order_status'] === 'cancelled' ? 'Huỷ đơn qua API' : null);
            $result = OrderHistoryRecorder::actingAs('api', $message, fn () => $this->orders->changeStatus($order, $data['order_status'], $reason), null, $actor)->fresh();

            // A cancel KIOT has not confirmed yet: accepted, finished by kiot:retry-outbox.
            return [$data['order_status'] === 'cancelled' && $result->order_status !== 'cancelled' ? 202 : 200, $result];
        });
    }

    public function payments(Request $request, int $order): JsonResponse
    {
        return $this->run($request, 'order.payment', $order, true, function (Order $order, string $actor) use ($request) {
            $data = Validator::make($request->all(), [
                'amount' => ['required', 'integer', 'min:1', 'max:10000000000'],
                'paid_at' => ['nullable', 'date'],
                'method' => ['nullable', Rule::in(OrderWriteService::PAYMENT_METHODS)],
                'reference' => ['nullable', 'string', 'max:100'],
            ])->validate();
            $result = OrderHistoryRecorder::actingAs('api', null, fn () => $this->orders->recordPayment(
                $order, (int) $data['amount'], $data['paid_at'] ?? null, $data['method'] ?? null,
                filled($data['reference'] ?? null) ? trim($data['reference']) : null,
            ), null, $actor);

            return [200, $result->fresh()];
        });
    }

    public function shipment(Request $request, int $order): JsonResponse
    {
        return $this->run($request, 'order.shipment', $order, false, function (Order $order, string $actor) use ($request) {
            $data = Validator::make($request->all(), [
                'tracking_code' => ['sometimes', 'nullable', 'string', 'max:100'],
                'shipping_carrier' => ['sometimes', 'nullable', 'string', 'max:100'],
                'delivered' => ['sometimes', 'boolean'],
            ])->after(function ($validator) use ($request) {
                if (! $request->hasAny(['tracking_code', 'shipping_carrier', 'delivered'])) {
                    $validator->errors()->add('tracking_code', 'Gửi ít nhất một trong tracking_code, shipping_carrier, delivered.');
                }
            })->validate();
            $result = OrderHistoryRecorder::actingAs('api', null, fn () => $this->orders->updateShipment($order, $data), null, $actor);

            return [200, $result->fresh()];
        });
    }

    /**
     * @param  callable(Order, string): array{0: int, 1: Order}  $handler
     */
    private function run(Request $request, string $action, int $orderId, bool $requireKey, callable $handler): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        $tokenId = $token instanceof PersonalAccessToken ? $token->id : null;
        $tokenName = $token instanceof PersonalAccessToken ? (string) (explode(':', (string) $token->name, 3)[2] ?? $token->name) : 'API';
        $key = trim((string) $request->header('Idempotency-Key', ''));
        $input = $request->all();
        $hash = hash('sha256', $action.'|'.$orderId.'|'.json_encode(self::sorted($input), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $base = [
            'token_id' => $tokenId, 'token_name' => mb_substr($tokenName, 0, 255), 'action' => $action,
            'target_type' => 'order', 'target_id' => $orderId, 'order_id' => $orderId,
            'request_hash' => $hash, 'request' => $input, 'ip' => $request->ip(), 'created_at' => now(),
        ];

        if (! AdminApiTokens::mayEditOrders($request->user()) || ! AdminApiTokens::mayReadOrders($request->user())) {
            return $this->finish(AdminApiWriteLog::create($base), 403, 'FORBIDDEN', 'Người tạo token không còn quyền sửa đơn hàng.');
        }
        if ($key === '' && $requireKey) {
            return $this->finish(AdminApiWriteLog::create($base), 422, 'IDEMPOTENCY_KEY_REQUIRED', 'Thiếu header Idempotency-Key (mỗi khoản thanh toán một khoá riêng, ví dụ UUID).');
        }
        if (mb_strlen($key) > 100) {
            return $this->finish(AdminApiWriteLog::create($base), 422, 'IDEMPOTENCY_KEY_INVALID', 'Idempotency-Key tối đa 100 ký tự.');
        }

        $log = null;
        if ($key !== '') {
            $previous = AdminApiWriteLog::where('token_id', $tokenId)->where('idempotency_key', $key)->first();
            if ($previous) {
                return $this->replay($previous, $base, $hash);
            }
            try {
                // Reserved before the change so a parallel retry cannot apply it twice.
                $log = AdminApiWriteLog::create($base + ['idempotency_key' => $key]);
            } catch (UniqueConstraintViolationException) {
                return $this->replay(AdminApiWriteLog::where('token_id', $tokenId)->where('idempotency_key', $key)->firstOrFail(), $base, $hash);
            }
        }
        $log ??= AdminApiWriteLog::create($base);

        $order = Order::find($orderId);
        if (! $order) {
            return $this->finish($log, 404, 'ORDER_NOT_FOUND', 'Không tìm thấy đơn hàng.');
        }
        try {
            [$status, $result] = $handler($order, 'Token: '.$tokenName);
        } catch (ValidationException $exception) {
            return $this->finish($log, 422, 'VALIDATION_FAILED', $exception->getMessage(), ['errors' => $exception->errors()]);
        } catch (OrderWriteException $exception) {
            return $this->finish($log, $exception->httpStatus, $exception->errorCode, $exception->getMessage());
        } catch (Throwable $exception) {
            // Unexpected error: free the key so the caller can retry it.
            $log->update(['status_code' => 500, 'idempotency_key' => null, 'error_code' => 'INTERNAL_ERROR']);
            throw $exception;
        }

        $body = ['data' => $this->presenter->present($result->load(['items.product', 'affiliate', 'affiliateCommission']))];
        $log->update(['status_code' => $status, 'response' => $body]);

        return $this->respond($body, $status);
    }

    /** Same key: same body gets the stored answer again; another body is refused. */
    private function replay(AdminApiWriteLog $previous, array $base, string $hash): JsonResponse
    {
        if ($previous->request_hash !== $hash || $previous->action !== $base['action'] || (int) $previous->target_id !== (int) $base['target_id']) {
            return $this->finish(AdminApiWriteLog::create($base), 409, 'IDEMPOTENCY_KEY_REUSED', 'Idempotency-Key này đã dùng cho một yêu cầu khác. Dùng khoá mới cho mỗi khoản.');
        }
        if ($previous->status_code === 0) {
            return $this->respond(['message' => 'Yêu cầu với Idempotency-Key này đang được xử lý, thử lại sau ít giây.', 'error_code' => 'IDEMPOTENCY_IN_PROGRESS'], 409);
        }

        return $this->respond((array) $previous->response, $previous->status_code)->header('Idempotent-Replayed', 'true');
    }

    private function finish(AdminApiWriteLog $log, int $status, string $code, string $message, array $extra = []): JsonResponse
    {
        $body = ['message' => $message, 'error_code' => $code] + $extra;
        $log->update(['status_code' => $status, 'response' => $body, 'error_code' => $code]);

        return $this->respond($body, $status);
    }

    private function respond(array $body, int $status): JsonResponse
    {
        return response()->json($body, $status)->header('Cache-Control', 'no-store, private');
    }

    private static function sorted(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map([self::class, 'sorted'], $value);
    }
}
