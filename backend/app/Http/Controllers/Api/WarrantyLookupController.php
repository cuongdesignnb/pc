<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\KiotIntegrationException;
use App\Http\Controllers\Controller;
use App\Services\Integrations\Kiot\KiotClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GET /api/v1/warranty-lookup (/tra-cuu-bao-hanh and the Zalo Mini App).
 *
 * Asks KIOT by serial / service tag, or by phone plus the last 4 characters of
 * the serial. Limited to 5 lookups a minute per IP (route throttle); after
 * repeated lookups with no result the IP must pass a Cloudflare Turnstile
 * captcha, when the shop has set it up.
 */
class WarrantyLookupController extends Controller
{
    /** Lookups with no result before the captcha is asked for. */
    public const MISSES_BEFORE_CAPTCHA = 3;

    private const MISS_TTL_SECONDS = 1800;

    private const FIELDS = ['product_name', 'serial', 'purchase_date', 'warranty_months', 'warranty_end_date', 'days_left', 'status', 'customer_phone'];

    public function __invoke(Request $request, KiotClient $kiot): JsonResponse
    {
        $validated = $request->validate([
            'serial' => ['nullable', 'string', 'min:4', 'max:100', 'regex:/^[\pL\pN\-_.\/ ]+$/u', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+ .\-]+$/', 'required_without:serial'],
            'serial_last4' => ['nullable', 'string', 'regex:/^[\pL\pN]{4}$/u', 'required_with:phone'],
            'captcha_token' => ['nullable', 'string', 'max:2048'],
        ], [
            'serial.required_without' => 'Vui lòng nhập số serial / service tag hoặc số điện thoại.',
            'phone.required_without' => 'Vui lòng nhập số serial / service tag hoặc số điện thoại.',
            'serial_last4.required_with' => 'Tra theo số điện thoại cần thêm 4 ký tự cuối của số serial.',
            'serial_last4.regex' => '4 ký tự cuối của số serial gồm chữ hoặc số.',
            'serial.min' => 'Số serial cần ít nhất 4 ký tự.',
        ]);

        $missKey = 'warranty-lookup:misses:'.sha1((string) $request->ip());
        if ($this->captchaRequired($missKey) && ! $this->captchaPasses($validated['captcha_token'] ?? null, $request->ip())) {
            return response()->json([
                'message' => 'Vui lòng xác nhận bạn không phải robot rồi tra cứu lại.',
                'captcha_required' => true,
            ], 422);
        }

        $query = ! empty($validated['serial'])
            ? ['serial' => trim($validated['serial'])]
            : ['phone' => trim($validated['phone']), 'serial_last4' => $validated['serial_last4']];

        try {
            $response = $kiot->warranties($query);
        } catch (KiotIntegrationException|Throwable $exception) {
            Log::warning('Warranty lookup: KIOT unavailable', ['exception' => $exception::class]);

            return $this->unavailable();
        }
        if (! $response->successful()) {
            Log::warning('Warranty lookup: KIOT error', ['status' => $response->status, 'code' => $response->errorCode()]);

            return $this->unavailable();
        }

        $results = collect($response->data())
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => array_intersect_key($row, array_flip(self::FIELDS)))
            ->values()->all();

        if ($results === []) {
            Cache::put($missKey, (int) Cache::get($missKey, 0) + 1, self::MISS_TTL_SECONDS);
        } else {
            Cache::forget($missKey);
        }

        return response()->json([
            'data' => $results,
            'captcha_required' => $this->captchaRequired($missKey),
        ]);
    }

    private function captchaRequired(string $missKey): bool
    {
        return filled(config('services.turnstile.secret_key'))
            && (int) Cache::get($missKey, 0) >= self::MISSES_BEFORE_CAPTCHA;
    }

    private function captchaPasses(?string $token, ?string $ip): bool
    {
        if (blank($token)) {
            return false;
        }
        try {
            return (bool) Http::asForm()->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array_filter([
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]))->json('success', false);
        } catch (Throwable) {
            return false;
        }
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'message' => 'Hệ thống tra cứu bảo hành đang bận. Vui lòng thử lại sau hoặc gọi hotline để được hỗ trợ.',
        ], 503);
    }
}
