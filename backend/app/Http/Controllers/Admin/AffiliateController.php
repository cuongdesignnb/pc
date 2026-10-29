<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateCustomer;
use App\Models\AffiliateExternalOrder;
use App\Models\Category;
use App\Services\Affiliate\AffiliateCheckoutService;
use App\Services\Affiliate\CommissionService;
use App\Services\Affiliate\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Admin side of the CTV program: approve CTVs, move customers, confirm commissions, record shop sales. */
class AffiliateController extends Controller
{
    public function index(Request $request)
    {
        $query = Affiliate::with('user:id,name,email')
            ->withCount('customers')
            ->withSum(['commissions as pending_amount' => fn ($q) => $q->where('status', 'pending')], 'amount')
            ->withSum(['commissions as approved_amount' => fn ($q) => $q->where('status', 'approved')], 'amount')
            ->latest('id');
        if (in_array($request->status, Affiliate::STATUSES, true)) {
            $query->where('status', $request->status);
        }
        if ($search = trim((string) $request->search)) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', '%'.PhoneNumber::normalize($search).'%')
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        return Inertia::render('Admin/Affiliates/Index', [
            'affiliates' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function show(Affiliate $affiliate, CommissionService $commissions)
    {
        $affiliate->load('user:id,name,email,phone');

        return Inertia::render('Admin/Affiliates/Show', [
            'affiliate' => $affiliate,
            'wallet' => $commissions->wallet($affiliate),
            'customers' => $affiliate->customers()->latest('id')->limit(200)->get(),
            'commissions' => $affiliate->commissions()->with('order:id,order_number,order_status')->latest('id')->limit(50)->get(),
            'clicks' => $affiliate->clicks()->latest('id')->limit(30)->get(),
            'clickCount' => $affiliate->clicks()->count(),
            'otherAffiliates' => Affiliate::with('user:id,name')->where('status', 'active')->whereKeyNot($affiliate->id)
                ->orderBy('code')->get(['id', 'code', 'user_id'])
                ->map(fn (Affiliate $other) => ['id' => $other->id, 'label' => $other->label()]),
        ]);
    }

    public function updateStatus(Request $request, Affiliate $affiliate)
    {
        $validated = $request->validate(['status' => 'required|in:active,locked', 'note' => 'nullable|string|max:1000']);

        DB::transaction(function () use ($affiliate, $validated) {
            $data = ['status' => $validated['status']];
            if (array_key_exists('note', $validated)) {
                $data['note'] = $validated['note'];
            }
            if ($validated['status'] === 'active' && ! $affiliate->code) {
                $data['code'] = Affiliate::nextCode();
                $data['approved_at'] = now();
            }
            $affiliate->update($data);
        });

        return back()->with('success', $validated['status'] === 'active'
            ? "Đã duyệt CTV {$affiliate->code}."
            : 'Đã khoá CTV.');
    }

    /** Only an admin may move a customer to another CTV. */
    public function reassignCustomer(Request $request, AffiliateCustomer $customer)
    {
        $validated = $request->validate(['affiliate_id' => 'required|integer|exists:affiliates,id']);
        $customer->update([
            'affiliate_id' => $validated['affiliate_id'],
            'source' => 'admin',
            'assigned_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Đã chuyển khách sang CTV khác.');
    }

    public function commissions(Request $request)
    {
        $query = AffiliateCommission::with(['affiliate.user:id,name', 'order:id,order_number,order_status,delivered_at', 'externalOrder:id,kiot_invoice_code,customer_phone'])
            ->latest('id');
        if (in_array($request->status, AffiliateCommission::STATUSES, true)) {
            $query->where('status', $request->status);
        }
        if ($request->affiliate_id) {
            $query->where('affiliate_id', (int) $request->affiliate_id);
        }

        return Inertia::render('Admin/Affiliates/Commissions', [
            'commissions' => $query->paginate(30)->withQueryString()->through(fn (AffiliateCommission $c) => [
                'id' => $c->id,
                'affiliate' => $c->affiliate ? ['id' => $c->affiliate->id, 'label' => $c->affiliate->label()] : null,
                'order' => $c->order ? ['id' => $c->order->id, 'number' => $c->order->order_number, 'status' => $c->order->order_status] : null,
                'external' => $c->externalOrder ? ['code' => $c->externalOrder->kiot_invoice_code, 'phone' => $c->externalOrder->customer_phone] : null,
                'base_amount' => (int) $c->base_amount,
                'amount' => (int) $c->amount,
                'status' => $c->status,
                'approve_after' => $c->approve_after,
                'approved_at' => $c->approved_at,
                'cancelled_reason' => $c->cancelled_reason,
                'breakdown' => $c->breakdown ?? [],
                'created_at' => $c->created_at,
            ]),
            'affiliates' => $this->affiliateOptions(),
            'filters' => $request->only(['status', 'affiliate_id']),
        ]);
    }

    public function approveCommission(Request $request, AffiliateCommission $commission, CommissionService $commissions)
    {
        $commissions->approve($commission, $request->user());

        return back()->with('success', 'Đã xác nhận hoa hồng, CTV có thể rút.');
    }

    public function cancelCommission(Request $request, AffiliateCommission $commission, CommissionService $commissions)
    {
        $validated = $request->validate(['reason' => 'required|string|max:255']);
        $commissions->cancel($commission, $validated['reason']);

        return back()->with('success', 'Đã huỷ hoa hồng.');
    }

    public function externalOrders()
    {
        return Inertia::render('Admin/Affiliates/ExternalOrders', [
            'orders' => AffiliateExternalOrder::with(['affiliate.user:id,name', 'category:id,name', 'commission:id,external_order_id,amount,status'])
                ->latest('id')->paginate(30),
            'affiliates' => $this->affiliateOptions(),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'parent_id']),
        ]);
    }

    /**
     * A sale by phone or in the shop. The phone's CTV wins; a CTV code is
     * only needed (and only used) when the phone has no CTV yet.
     */
    public function storeExternalOrder(Request $request, AffiliateCheckoutService $checkout, CommissionService $commissions)
    {
        $validated = $request->validate([
            'customer_phone' => 'required|string|max:20',
            'affiliate_code' => 'nullable|string|max:20',
            'kiot_invoice_code' => 'nullable|string|max:50',
            'category_id' => 'required|integer|exists:categories,id',
            'amount' => 'required|integer|min:1',
            'sold_at' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);
        $phone = PhoneNumber::normalize($validated['customer_phone']);
        $customer = AffiliateCustomer::with('affiliate')->where('phone', $phone)->first();
        $affiliate = $customer?->affiliate ?? Affiliate::findActiveByCode($validated['affiliate_code'] ?? null);
        if (! $affiliate?->isActive()) {
            return back()->withErrors(['affiliate_code' => $customer
                ? 'Khách này thuộc một CTV đã bị khoá.'
                : 'SĐT chưa thuộc CTV nào, vui lòng nhập mã CTV hợp lệ.']);
        }
        if (PhoneNumber::normalize($affiliate->phone) === $phone) {
            return back()->withErrors(['customer_phone' => 'SĐT người mua trùng SĐT của CTV, không tính hoa hồng.']);
        }

        DB::transaction(function () use ($validated, $phone, $customer, $affiliate, $checkout, $commissions, $request) {
            if (! $customer) {
                $checkout->attach($affiliate, $phone, null, 'code');
            }
            $external = AffiliateExternalOrder::create([
                'affiliate_id' => $affiliate->id,
                'customer_phone' => $phone,
                'kiot_invoice_code' => $validated['kiot_invoice_code'] ?? null,
                'category_id' => $validated['category_id'],
                'amount' => $validated['amount'],
                'sold_at' => $validated['sold_at'],
                'note' => $validated['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $commissions->createForExternalOrder($external);
        });

        return back()->with('success', "Đã ghi nhận đơn cho CTV {$affiliate->code}.");
    }

    private function affiliateOptions()
    {
        return Affiliate::with('user:id,name')->whereNotNull('code')->orderBy('code')->get(['id', 'code', 'user_id'])
            ->map(fn (Affiliate $affiliate) => ['id' => $affiliate->id, 'label' => $affiliate->label()]);
    }
}
