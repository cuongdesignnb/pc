<?php

namespace App\Services\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateCustomer;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Works out which CTV a checkout belongs to and the first-order referral
 * discount, then records the customer and commission once the order exists.
 *
 * A phone number that already belongs to a CTV stays with that CTV whatever
 * code is typed; only an admin can move it.
 */
class AffiliateCheckoutService
{
    public function __construct(private readonly CommissionService $commissions) {}

    /**
     * @param  list<int>  $productIds
     * @return array{affiliate: Affiliate|null, customer: AffiliateCustomer|null, code: string|null, discount: int, message: string|null}
     */
    public function evaluate(?string $phone, ?string $code, array $productIds, int $subtotal): array
    {
        $phone = PhoneNumber::normalize($phone);
        $code = strtoupper(trim((string) $code)) ?: null;
        $result = ['affiliate' => null, 'customer' => null, 'code' => null, 'discount' => 0, 'message' => null];

        $customer = $phone === '' ? null : AffiliateCustomer::with('affiliate.user')->where('phone', $phone)->first();
        if ($customer) {
            $result['customer'] = $customer;
            $affiliate = $customer->affiliate?->isActive() ? $customer->affiliate : null;
        } else {
            $affiliate = Affiliate::findActiveByCode($code);
            if ($code !== null && ! $affiliate) {
                $result['message'] = 'Mã giới thiệu không hợp lệ.';

                return $result;
            }
        }
        if (! $affiliate) {
            return $result;
        }
        if ($phone !== '' && PhoneNumber::normalize($affiliate->phone) === $phone) {
            $result['message'] = 'Không áp dụng mã giới thiệu cho chính cộng tác viên.';

            return $result;
        }

        $affiliate->loadMissing('user');
        $result['affiliate'] = $affiliate;
        $result['code'] = $affiliate->code;
        if ($customer?->first_discount_order_id) {
            return $result;
        }
        if ($this->containsLaptop($productIds) || $subtotal >= $this->minNonLaptopSubtotal()) {
            $result['discount'] = min($subtotal, max(0, (int) Setting::get('affiliate_referral_discount', 200000)));
        } else {
            $result['message'] = 'Đơn linh kiện, phụ kiện, sửa chữa từ '
                .number_format($this->minNonLaptopSubtotal(), 0, ',', '.').'đ mới được giảm giá giới thiệu.';
        }

        return $result;
    }

    /** Called inside the order transaction once the order and its items exist. */
    public function recordOrder(Order $order, array $evaluation): void
    {
        $affiliate = $evaluation['affiliate'] ?? null;
        if (! $affiliate instanceof Affiliate) {
            return;
        }

        $customer = $evaluation['customer'] ?? $this->attach($affiliate, $order->shipping_phone, $order->user_id, 'link');
        if ($evaluation['discount'] > 0 && ! $customer->first_discount_order_id) {
            $customer->update(['first_discount_order_id' => $order->id]);
        }
        if (! $customer->user_id && $order->user_id) {
            $customer->update(['user_id' => $order->user_id]);
        }

        $this->commissions->createForOrder($order);
    }

    /** First CTV wins: an existing claim on the phone is returned untouched. */
    public function attach(Affiliate $affiliate, string $phone, ?int $userId, string $source): AffiliateCustomer
    {
        $phone = PhoneNumber::normalize($phone);
        try {
            return AffiliateCustomer::firstOrCreate(
                ['phone' => $phone],
                ['affiliate_id' => $affiliate->id, 'user_id' => $userId, 'source' => $source],
            );
        } catch (UniqueConstraintViolationException) {
            return AffiliateCustomer::where('phone', $phone)->firstOrFail();
        }
    }

    /** Products in a laptop category always get the discount, whatever the order total. */
    private function containsLaptop(array $productIds): bool
    {
        $laptopIds = $this->laptopCategoryIds();
        if ($laptopIds->isEmpty() || $productIds === []) {
            return false;
        }

        foreach (Product::with('category.parent.parent.parent')->whereIn('id', $productIds)->get() as $product) {
            $category = $product->category;
            for ($depth = 0; $category instanceof Category && $depth < 5; $depth++) {
                if ($laptopIds->contains($category->id)) {
                    return true;
                }
                $category = $category->parent;
            }
        }

        return false;
    }

    /** Categories picked in Cài đặt, or else the top-level categories named "Laptop …". */
    private function laptopCategoryIds(): Collection
    {
        $slugs = Setting::get('affiliate_laptop_category_slugs', []);
        $slugs = is_array($slugs) ? array_values(array_filter($slugs, 'is_string')) : [];

        return $slugs !== []
            ? Category::whereIn('slug', $slugs)->pluck('id')
            : Category::whereNull('parent_id')->where('name', 'like', 'Laptop%')->pluck('id');
    }

    private function minNonLaptopSubtotal(): int
    {
        return max(0, (int) Setting::get('affiliate_discount_min_non_laptop', 2000000));
    }
}
