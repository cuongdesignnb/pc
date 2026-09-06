<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\CartService;
use App\Services\Catalog\ProductPurchasabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    /**
     * Return the current cart, its selected-item summary and real product
     * relations for recommendations.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->cartResponse($this->getOrCreateCart($request));
    }

    public function recommendations(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);
        $groups = $this->cartService->recommendationGroups($this->cartService->load($cart));

        return response()->json([
            'accessories' => \App\Http\Resources\ProductCardResource::collection($groups['accessories'])->resolve($request),
            'recommendations' => \App\Http\Resources\ProductCardResource::collection($groups['recommendations'])->resolve($request),
        ]);
    }

    public function addItem(Request $request, ProductPurchasabilityService $purchasability): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->getOrCreateCart($request);
        DB::transaction(function () use ($validated, $cart, $purchasability): void {
            $product = Product::with('category')->lockForUpdate()->findOrFail($validated['product_id']);
            $variant = null;
            if (! empty($validated['variant_id'])) {
                $variant = ProductVariant::query()
                    ->whereKey($validated['variant_id'])
                    ->where('product_id', $product->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();
            }

            if (! $product->isVisibleOnStorefront() || (($validated['variant_id'] ?? null) && ! $variant)) {
                abort(422, 'Sản phẩm hoặc biến thể đã chọn không còn khả dụng.');
            }

            $cartItem = $cart->items()
                ->where('product_id', $product->id)
                ->when(
                    $variant,
                    fn ($query) => $query->where('variant_id', $variant->id),
                    fn ($query) => $query->whereNull('variant_id'),
                )
                ->lockForUpdate()
                ->first();
            $requestedQuantity = (int) $validated['quantity'] + (int) ($cartItem?->quantity ?? 0);
            $isAvailable = $variant
                ? $variant->stock_quantity >= $requestedQuantity
                : $purchasability->isPurchasable($product, $requestedQuantity);

            if (! $isAvailable) {
                abort(422, 'Sản phẩm không còn đủ số lượng khả dụng.');
            }

            $attributes = [
                'quantity' => $requestedQuantity,
                'price' => $variant?->display_price ?? $purchasability->unitPrice($product),
            ];
            if ($cartItem) {
                $cartItem->update($attributes);
            } else {
                $cart->items()->create($attributes + [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'is_selected' => true,
                ]);
            }
        });

        return $this->cartResponse($cart->fresh(), 'Đã thêm vào giỏ hàng');
    }

    public function updateItem(Request $request, CartItem $cartItem, ProductPurchasabilityService $purchasability): JsonResponse
    {
        $validated = $request->validate(['quantity' => 'required|integer|min:1']);
        [$cart, $item] = $this->ownedItem($request, $cartItem);
        $item->load(['product.category', 'variant']);

        $product = $item->product;
        $variant = $item->variant;
        $isAvailable = $product
            && $product->isVisibleOnStorefront()
            && ($variant
                ? $variant->is_active && $variant->stock_quantity >= (int) $validated['quantity']
                : $purchasability->isPurchasable($product, (int) $validated['quantity']));
        if (! $isAvailable) {
            return response()->json(['message' => 'Sản phẩm không còn đủ số lượng khả dụng.'], 422);
        }

        $item->update(['quantity' => $validated['quantity']]);

        return $this->cartResponse($cart->fresh(), 'Đã cập nhật giỏ hàng');
    }

    public function updateSelection(Request $request, CartItem $cartItem): JsonResponse
    {
        $validated = $request->validate(['selected' => 'required|boolean']);
        [$cart, $item] = $this->ownedItem($request, $cartItem);
        $item->update(['is_selected' => (bool) $validated['selected']]);

        return $this->cartResponse($cart->fresh(), 'Đã cập nhật sản phẩm được chọn');
    }

    public function selectAll(Request $request): JsonResponse
    {
        $validated = $request->validate(['selected' => 'required|boolean']);
        $cart = $this->getOrCreateCart($request);
        $cart->items()->update(['is_selected' => (bool) $validated['selected']]);

        return $this->cartResponse($cart->fresh(), 'Đã cập nhật lựa chọn giỏ hàng');
    }

    public function removeItem(Request $request, CartItem $cartItem): JsonResponse
    {
        [$cart, $item] = $this->ownedItem($request, $cartItem);
        $item->delete();

        return $this->cartResponse($cart->fresh(), 'Đã xóa khỏi giỏ hàng');
    }

    public function removeItems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_ids' => 'required|array|min:1|max:100',
            'item_ids.*' => 'required|integer',
        ]);
        $cart = $this->getOrCreateCart($request);
        $itemIds = collect($validated['item_ids'])->map(fn ($id): int => (int) $id)->unique()->values();
        $items = $cart->items()->whereIn('id', $itemIds->all())->get();
        if ($items->count() !== $itemIds->count()) {
            abort(404, 'Một hoặc nhiều sản phẩm không thuộc giỏ hàng hiện tại.');
        }
        $cart->items()->whereIn('id', $itemIds->all())->delete();

        return $this->cartResponse($cart->fresh(), 'Đã xóa các sản phẩm được chọn');
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);
        $cart->items()->delete();

        return $this->cartResponse($cart->fresh(), 'Đã xóa giỏ hàng');
    }

    private function cartResponse(Cart $cart, ?string $message = null): JsonResponse
    {
        $this->cartService->load($cart);
        $groups = $this->cartService->recommendationGroups($cart);
        $payload = (new CartResource(
            $cart,
            $this->cartService->summary($cart),
            $groups['recommendations'],
            $groups['accessories'],
            $this->cartService->benefits(),
            $this->cartService->paymentMethods(),
            $this->cartService->support(),
        ))->resolve(request());
        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload);
    }

    /** @return array{0: Cart, 1: CartItem} */
    private function ownedItem(Request $request, CartItem $boundItem): array
    {
        $cart = $this->getOrCreateCart($request);
        $item = $cart->items()->whereKey($boundItem->id)->firstOrFail();

        return [$cart, $item];
    }

    private function getOrCreateCart(Request $request): Cart
    {
        $user = $request->user('sanctum') ?? $request->user();
        if ($user) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        $sessionId = $request->header('X-Cart-Session') ?? session()->getId();

        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }
}
