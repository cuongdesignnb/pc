<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateCommission;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Affiliate\CommissionRules;
use App\Services\Affiliate\CommissionService;
use App\Services\Affiliate\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Storefront side of the CTV program: link clicks, sign-up and the CTV's own dashboard. */
class AffiliateController extends Controller
{
    /** A visitor arrived through ?ref=CODE. */
    public function click(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20',
            'url' => 'nullable|string|max:1000',
        ]);
        $affiliate = Affiliate::findActiveByCode($validated['code']);
        if (! $affiliate) {
            return response()->json(['valid' => false]);
        }

        AffiliateClick::create([
            'affiliate_id' => $affiliate->id,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            'landing_url' => $validated['url'] ?? null,
        ]);

        return response()->json(['valid' => true, 'code' => $affiliate->code, 'name' => $affiliate->user?->name]);
    }

    public function register(Request $request): JsonResponse
    {
        $user = $request->user();
        $existing = $user->affiliate;
        if ($existing && $existing->status !== 'pending') {
            return response()->json(['message' => 'Tài khoản đã đăng ký cộng tác viên.'], 422);
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+().\s-]{9,20}$/'],
            'bank_name' => 'required|string|max:100',
            'bank_account_number' => 'required|string|max:50',
            'bank_account_name' => 'required|string|max:100',
            'id_card_number' => [Rule::requiredIf((bool) Setting::get('affiliate_id_card_required', false)), 'nullable', 'string', 'max:20'],
        ]);
        $validated['phone'] = PhoneNumber::normalize($validated['phone']);

        $affiliate = Affiliate::updateOrCreate(['user_id' => $user->id], $validated + ['status' => 'pending']);
        $user->setRelation('affiliate', $affiliate);

        return response()->json(['affiliate' => $this->profile($affiliate)], $existing ? 200 : 201);
    }

    public function me(Request $request, CommissionService $commissions): JsonResponse
    {
        $affiliate = $request->user()->affiliate;
        if (! $affiliate) {
            return response()->json([
                'affiliate' => null,
                'id_card_required' => (bool) Setting::get('affiliate_id_card_required', false),
            ]);
        }

        $history = $affiliate->commissions()
            ->with(['order:id,order_number,created_at,order_status', 'externalOrder:id,kiot_invoice_code,sold_at'])
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'affiliate' => $this->profile($affiliate),
            'id_card_required' => (bool) Setting::get('affiliate_id_card_required', false),
            'stats' => [
                'clicks' => $affiliate->clicks()->count(),
                'customers' => $affiliate->customers()->count(),
                'orders' => $affiliate->commissions()->where('status', '!=', 'cancelled')->count(),
            ],
            'wallet' => $commissions->wallet($affiliate) + [
                'min_payout' => (int) Setting::get('affiliate_min_payout', 100000),
            ],
            'commissions' => [
                'data' => collect($history->items())->map(fn (AffiliateCommission $commission) => [
                    'id' => $commission->id,
                    'order_number' => $commission->order?->order_number
                        ?? ($commission->externalOrder?->kiot_invoice_code ?: 'Đơn tại cửa hàng'),
                    'base_amount' => (int) $commission->base_amount,
                    'amount' => (int) $commission->amount,
                    'status' => $commission->status,
                    'approve_after' => $commission->approve_after?->toIso8601String(),
                    'cancelled_reason' => $commission->cancelled_reason,
                    'created_at' => $commission->created_at?->toIso8601String(),
                ])->values(),
                'meta' => ['current_page' => $history->currentPage(), 'last_page' => $history->lastPage(), 'total' => $history->total()],
            ],
        ]);
    }

    public function updatePayoutSchedule(Request $request): JsonResponse
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);

        $validated = $request->validate([
            'payout_days' => 'required|array|min:1|max:3',
            'payout_days.*' => 'required|integer|distinct|min:1|max:28',
        ]);
        $days = collect($validated['payout_days'])->map(fn ($day) => (int) $day)->sort()->values()->all();
        $affiliate->update(['payout_days' => $days]);

        return response()->json(['affiliate' => $this->profile($affiliate)]);
    }

    /** What the signed-in CTV earns on one product, for the share box on the product page. */
    public function productCommission(Request $request, Product $product, CommissionRules $rules): JsonResponse
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate?->isActive(), 403);

        $product->loadMissing('category.parent.parent.parent');
        $rule = $rules->forProduct($product);
        $price = $product->storefrontSalePrice() ?? $product->storefrontRegularPrice();

        return response()->json([
            'code' => $affiliate->code,
            'commission' => $rules->describe($rule, $price),
            'estimated_amount' => $rule ? $rules->amount($rule, $price, 1) : 0,
        ]);
    }

    private function profile(Affiliate $affiliate): array
    {
        return [
            'code' => $affiliate->code,
            'status' => $affiliate->status,
            'phone' => $affiliate->phone,
            'bank_name' => $affiliate->bank_name,
            'bank_account_number' => $affiliate->bank_account_number,
            'bank_account_name' => $affiliate->bank_account_name,
            'payout_days' => $affiliate->payout_days ?? [],
            'approved_at' => $affiliate->approved_at?->toIso8601String(),
        ];
    }
}
