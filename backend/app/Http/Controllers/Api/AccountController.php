<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountAddressResource;
use App\Http\Resources\AccountOrderSummaryResource;
use App\Http\Resources\AccountSavedBuildResource;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\UserProfileResource;
use App\Models\Address;
use App\Models\Banner;
use App\Models\ComponentType;
use App\Models\Order;
use App\Models\Product;
use App\Models\SavedBuild;
use App\Services\Locations\LocationDirectory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('defaultAddress');

        $ordersQuery = Order::query()->where('user_id', $user->id);
        $recentOrders = (clone $ordersQuery)
            ->with(['items.product.images'])
            ->latest()
            ->limit(5)
            ->get();
        $savedBuilds = $this->hydrateSavedBuilds(
            SavedBuild::query()->where('user_id', $user->id)->latest()->limit(2)->get(),
        );
        $wishlistItems = $user->wishlistItems()
            ->with(['product.images', 'product.brand', 'product.category'])
            ->latest()
            ->limit(3)
            ->get();

        return $this->noStore(response()->json([
            'user' => UserProfileResource::make($user)->resolve($request),
            'stats' => [
                'orders' => (int) (clone $ordersQuery)->count(),
                'wishlist' => (int) $user->wishlistItems()->count(),
                'saved_builds' => (int) $user->savedBuilds()->count(),
                'total_spent' => (int) (clone $ordersQuery)->where('order_status', 'delivered')->sum('total'),
            ],
            'loyalty' => null,
            'recent_orders' => AccountOrderSummaryResource::collection($recentOrders)->resolve($request),
            'saved_builds' => AccountSavedBuildResource::collection($savedBuilds)->resolve($request),
            'wishlist' => ProductCardResource::collection($wishlistItems->pluck('product')->filter())->resolve($request),
            'banner' => $this->accountBanner(),
            'capabilities' => [
                'loyalty' => false,
                'addresses' => true,
                'wishlist' => true,
                'saved_builds' => true,
                'warranty' => true,
            ],
        ]));
    }

    public function orders(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.product.images'])
            ->latest()
            ->paginate(10);

        return $this->noStore(response()->json([
            'orders' => AccountOrderSummaryResource::collection($orders->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]));
    }

    public function order(Request $request, Order $order): JsonResponse
    {
        $order = Order::query()
            ->whereKey($order->id)
            ->where('user_id', $request->user()->id)
            ->with(['items.product.images'])
            ->firstOrFail();

        $summary = AccountOrderSummaryResource::make($order)->resolve($request);
        $summary['items'] = $order->items->map(fn ($item): array => [
            'id' => (int) $item->id,
            'product_id' => $item->product_id === null ? null : (int) $item->product_id,
            'product_name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'sku' => $item->sku,
            'quantity' => (int) $item->quantity,
            'price' => (int) $item->price,
            'total' => (int) $item->total,
            'image' => $item->product?->images?->first() ? [
                'url' => $item->product->images->first()->url,
                'alt' => $item->product->images->first()->alt ?: $item->product_name,
            ] : null,
        ])->values()->all();
        $summary['shipping'] = [
            'name' => $order->shipping_name,
            'phone' => $order->shipping_phone,
            'email' => $order->customer_email,
            'address' => $order->shipping_address,
            'city' => $order->shipping_city,
            'district' => $order->shipping_district,
            'ward' => $order->shipping_ward,
        ];

        return $this->noStore(response()->json(['order' => $summary]));
    }

    public function savedBuilds(Request $request): JsonResponse
    {
        $builds = $this->hydrateSavedBuilds(
            SavedBuild::query()->where('user_id', $request->user()->id)->latest()->get(),
        );

        return $this->noStore(response()->json([
            'builds' => AccountSavedBuildResource::collection($builds)->resolve($request),
        ]));
    }

    public function addresses(Request $request): JsonResponse
    {
        return $this->noStore(response()->json([
            'addresses' => AccountAddressResource::collection(
                $request->user()->addresses()->latest('is_default')->latest('id')->get(),
            )->resolve($request),
        ]));
    }

    public function storeAddress(Request $request, LocationDirectory $locations): JsonResponse
    {
        $user = $request->user();
        $data = $this->validatedAddress($request, $locations);
        $makeDefault = (bool) ($data['is_default'] ?? false) || ! $user->addresses()->exists();

        $address = app(DatabaseManager::class)->transaction(function () use ($user, $data, $makeDefault): Address {
            if ($makeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create([
                ...$data,
                'is_default' => $makeDefault,
            ]);
        });

        return $this->noStore(response()->json([
            'message' => 'Đã thêm địa chỉ nhận hàng.',
            'address' => AccountAddressResource::make($address)->resolve($request),
        ], 201));
    }

    public function updateAddress(Request $request, Address $address, LocationDirectory $locations): JsonResponse
    {
        $address = $this->ownedAddress($request, $address);
        $data = $this->validatedAddress($request, $locations);
        $makeDefault = (bool) ($data['is_default'] ?? false);

        app(DatabaseManager::class)->transaction(function () use ($address, $data, $makeDefault): void {
            if ($makeDefault) {
                $address->user->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }
            $address->update([...$data, 'is_default' => $makeDefault || (bool) $address->is_default]);
        });

        return $this->noStore(response()->json([
            'message' => 'Đã cập nhật địa chỉ nhận hàng.',
            'address' => AccountAddressResource::make($address->fresh())->resolve($request),
        ]));
    }

    public function destroyAddress(Request $request, Address $address): JsonResponse
    {
        $address = $this->ownedAddress($request, $address);
        $wasDefault = (bool) $address->is_default;
        $user = $address->user;

        app(DatabaseManager::class)->transaction(function () use ($address, $user, $wasDefault): void {
            $address->delete();
            if ($wasDefault) {
                $user->addresses()->latest('id')->first()?->update(['is_default' => true]);
            }
        });

        return $this->noStore(response()->json(['message' => 'Đã xóa địa chỉ nhận hàng.']));
    }

    public function warranties(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('order_status', 'delivered')
            ->whereNotNull('delivered_at')
            ->with(['items.product.images'])
            ->latest('delivered_at')
            ->get();
        $warranties = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $order->delivered_at) {
                    continue;
                }
                $months = $item->warranty_months === null ? null : (int) $item->warranty_months;
                if ($months !== null && $months <= 0) {
                    continue;
                }
                $expiresAt = $months === null ? null : $order->delivered_at->copy()->addMonths($months);
                $warranties[] = [
                    'id' => 'order-item-'.$item->id,
                    'order_number' => $order->order_number,
                    'product_name' => $item->product_name,
                    'product_slug' => $product?->slug,
                    'image' => $product?->images?->first() ? [
                        'url' => $product->images->first()->url,
                        'alt' => $product->images->first()->alt ?: $item->product_name,
                    ] : null,
                    'warranty_months' => $months,
                    'purchased_at' => $order->delivered_at->toISOString(),
                    'expires_at' => $expiresAt?->toISOString(),
                    'status' => $expiresAt === null ? 'unknown' : ($expiresAt->isFuture() ? 'active' : 'expired'),
                ];
            }
        }

        return $this->noStore(response()->json([
            'warranties' => $warranties,
            'capabilities' => ['warranty' => $warranties !== []],
        ]));
    }

    /** @return array<string, mixed> */
    private function validatedAddress(Request $request, LocationDirectory $locations): array
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'province_code' => ['required', 'string', 'max:20'],
            'ward_code' => ['required', 'string', 'max:20'],
            'district' => ['nullable', 'string', 'max:100'],
            'street' => ['required', 'string', 'max:500'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        $resolved = $locations->resolve($validated['province_code'], $validated['ward_code']);
        if ($resolved === null) {
            throw ValidationException::withMessages([
                'ward_code' => ['Xã/phường không thuộc tỉnh/thành phố đã chọn.'],
            ]);
        }

        return [
            'label' => $validated['label'] ?? null,
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'],
            'province' => $resolved['province']['fullname'],
            'province_code' => $resolved['province']['code'],
            'district' => $validated['district'] ?? '',
            'ward' => $resolved['ward']['fullname'],
            'ward_code' => $resolved['ward']['code'],
            'street' => $validated['street'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
        ];
    }

    private function ownedAddress(Request $request, Address $address): Address
    {
        abort_unless((int) $address->user_id === (int) $request->user()->id, 404);

        return $address;
    }

    private function accountBanner(): ?array
    {
        $banner = Banner::query()->active()->where('position', 'account_dashboard')->orderBy('sort_order')->first();
        if (! $banner) {
            return null;
        }

        return [
            'id' => (int) $banner->id,
            'title' => $banner->title,
            'description' => $banner->description,
            'badge' => $banner->badge,
            'image' => \App\Support\PublicAssetUrl::normalize($banner->image),
            'link' => $banner->link,
        ];
    }

    private function hydrateSavedBuilds(EloquentCollection $builds): EloquentCollection
    {
        $productIds = $builds->flatMap(fn (SavedBuild $build): array => array_values($build->products ?? []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->with(['brand', 'category', 'componentType', 'images', 'specifications.specificationKey', 'powerRequirement'])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->get()
            ->keyBy('id');
        $typeIds = $builds->flatMap(fn (SavedBuild $build): array => array_keys($build->products ?? []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $types = ComponentType::query()->whereIn('id', $typeIds->all())->get()->keyBy('id');

        foreach ($builds as $build) {
            $resolved = collect($build->products ?? [])->mapWithKeys(function ($productId, $typeId) use ($products): array {
                $product = $products->get((int) $productId);

                return $product ? [(string) $typeId => $product] : [];
            });
            $build->setRelation('accountProducts', $resolved);
            $build->setRelation('accountComponentTypes', $types);
        }

        return $builds;
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response
            ->header('Cache-Control', 'private, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
