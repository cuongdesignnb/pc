<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->response($request);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);
        $product = $this->sellableProducts()->whereKey($validated['product_id'])->firstOrFail();

        WishlistItem::firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return $this->response($request, 'Đã thêm vào danh sách yêu thích.');
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        WishlistItem::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return $this->response($request, 'Đã xóa khỏi danh sách yêu thích.');
    }

    public function merge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['nullable', 'array', 'max:100'],
            // Deleted or retired products are filtered by sellableProducts()
            // below instead of rejecting the whole guest wishlist merge.
            'product_ids.*' => ['integer', 'distinct'],
        ]);
        $productIds = collect($validated['product_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();
        $sellableIds = $this->sellableProducts()->whereIn('id', $productIds->all())->pluck('id');

        foreach ($sellableIds as $productId) {
            WishlistItem::firstOrCreate([
                'user_id' => $request->user()->id,
                'product_id' => $productId,
            ]);
        }

        return $this->response($request, 'Đã đồng bộ danh sách yêu thích.');
    }

    private function response(Request $request, ?string $message = null): JsonResponse
    {
        $items = WishlistItem::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->get();
        $ids = $items->pluck('product_id')->map(fn ($id): int => (int) $id)->values();
        $products = $this->sellableProducts()
            ->whereIn('id', $ids->all())
            ->get()
            ->sortBy(fn (Product $product): int => $ids->search((int) $product->id))
            ->values();
        $payload = [
            'ids' => $ids,
            'products' => ProductCardResource::collection($products)->resolve($request),
        ];
        if ($message !== null) {
            $payload['message'] = $message;
        }

        return $this->noStore(response()->json($payload));
    }

    private function sellableProducts(): Builder
    {
        return Product::query()
            ->sellableOnline()
            ->with(['images', 'brand', 'category'])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->withCount([
                'variants as has_variants' => fn (Builder $query) => $query->where('is_active', true),
            ]);
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response
            ->header('Cache-Control', 'private, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
