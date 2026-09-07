<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Checkout\CheckoutQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutQuoteController extends Controller
{
    public function store(Request $request, CheckoutQuoteService $quotes): JsonResponse
    {
        $validated = $request->validate([
            'checkout_mode' => 'required|in:cart,buy_now',
            'items' => 'required_if:checkout_mode,buy_now|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.variant_id' => 'nullable|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_province_code' => 'nullable|string|max:20',
            'shipping_ward_code' => 'nullable|string|max:20',
            'shipping_method' => 'nullable|string|in:standard',
            'payment_method' => 'nullable|string|in:cod,sepay',
        ]);

        return response()->json($quotes->create($request, $validated));
    }
}
