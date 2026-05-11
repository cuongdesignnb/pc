<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
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

        $cart->load(['items.product.images', 'items.product.brand', 'items.product.category']);

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
    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->getOrCreateCart($request);
        $product = Product::findOrFail($validated['product_id']);

        // Check if product already in cart
        $cartItem = $cart->items()->where('product_id', $product->id)->first();

        if ($cartItem) {
            $cartItem->update([
                'quantity' => $cartItem->quantity + $validated['quantity'],
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
                'price' => $product->sale_price ?? $product->price,
            ]);
        }

        $cart->load(['items.product.images']);

        return response()->json([
            'message' => 'Đã thêm vào giỏ hàng',
            'cart' => $cart,
            'count' => $cart->items->sum('quantity'),
        ]);
    }

    /**
     * Update cart item quantity
     */
    public function updateItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cartItem->update(['quantity' => $validated['quantity']]);

        $cart = $cartItem->cart->load(['items.product.images']);

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

        $cart->load(['items.product.images']);

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
