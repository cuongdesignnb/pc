<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\ProductPurchasabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Get cart items
     */
    public function index(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);

        $cart->load(['items.product.images', 'items.product.brand', 'items.product.category', 'items.variant']);

        return response()->json([
            'cart' => $cart,
            'items' => $cart->items,
            'total' => $cart->items->sum(fn ($item) => $item->price * $item->quantity),
            'count' => $cart->items->sum('quantity'),
        ]);
    }

    /**
     * Add item to cart
     */
    public function addItem(Request $request, ProductPurchasabilityService $purchasability): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->getOrCreateCart($request);
        $product = Product::findOrFail($validated['product_id']);
        $variant = null;
        if (! empty($validated['variant_id'])) {
            $variant = ProductVariant::whereKey($validated['variant_id'])
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->first();
            if (! $variant || $variant->stock_quantity < (int) $validated['quantity'] || ! $product->isVisibleOnStorefront()) {
                return response()->json(['message' => 'Biến thể đã chọn không còn đủ số lượng khả dụng.'], 422);
            }
        }

        if (! $variant && ! $purchasability->isPurchasable($product, (int) $validated['quantity'])) {
            return response()->json(['message' => 'Sản phẩm không còn đủ số lượng khả dụng.'], 422);
        }

        // Check if product already in cart
        $cartItem = $cart->items()
            ->where('product_id', $product->id)
            ->when($variant, fn ($query) => $query->where('variant_id', $variant->id), fn ($query) => $query->whereNull('variant_id'))
            ->first();
        $unitPrice = $variant?->display_price ?? $purchasability->unitPrice($product);

        if ($cartItem) {
            $cartItem->update([
                'quantity' => $cartItem->quantity + $validated['quantity'],
                'price' => $unitPrice,
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => $validated['quantity'],
                'price' => $unitPrice,
            ]);
        }

        $cart->load(['items.product.images', 'items.variant']);

        return response()->json([
            'message' => 'Đã thêm vào giỏ hàng',
            'cart' => $cart,
            'count' => $cart->items->sum('quantity'),
        ]);
    }

    /**
     * Update cart item quantity
     */
    public function updateItem(Request $request, CartItem $cartItem, ProductPurchasabilityService $purchasability): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $product = $cartItem->product;
        $variant = $cartItem->variant;
        $isAvailable = $variant
            ? $variant->is_active && $variant->stock_quantity >= (int) $validated['quantity'] && $product->isVisibleOnStorefront()
            : $purchasability->isPurchasable($product, (int) $validated['quantity']);
        if (! $isAvailable) {
            return response()->json(['message' => 'Sản phẩm không còn đủ số lượng khả dụng.'], 422);
        }
        $cartItem->update(['quantity' => $validated['quantity']]);

        $cart = $cartItem->cart->load(['items.product.images', 'items.variant']);

        return response()->json([
            'message' => 'Đã cập nhật giỏ hàng',
            'cart' => $cart,
            'total' => $cart->items->sum(fn ($item) => $item->price * $item->quantity),
        ]);
    }

    /**
     * Remove item from cart
     */
    public function removeItem(CartItem $cartItem): JsonResponse
    {
        $cart = $cartItem->cart;
        $cartItem->delete();

        $cart->load(['items.product.images', 'items.variant']);

        return response()->json([
            'message' => 'Đã xóa khỏi giỏ hàng',
            'cart' => $cart,
            'count' => $cart->items->sum('quantity'),
        ]);
    }

    /**
     * Clear cart
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);
        $cart->items()->delete();

        return response()->json([
            'message' => 'Đã xóa giỏ hàng',
        ]);
    }

    /**
     * Get or create cart for user/session
     */
    private function getOrCreateCart(Request $request): Cart
    {
        if ($request->user()) {
            return Cart::firstOrCreate(['user_id' => $request->user()->id]);
        }

        $sessionId = $request->header('X-Cart-Session') ?? session()->getId();

        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }
}
