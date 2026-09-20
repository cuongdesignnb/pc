<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProductContentCampaign;
use App\Models\AiProductContentCampaignItem;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Ai\ProductContactFooterService;
use App\Services\Ai\ProductContentCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AiProductContentCampaignController extends Controller
{
    public function __construct(
        private readonly ProductContentCampaignService $campaigns,
        private readonly ProductContactFooterService $footer,
    ) {}

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        return Inertia::render('Admin/AiProductCampaigns/Index', [
            'campaigns' => AiProductContentCampaign::with('creator:id,name')->latest()->paginate(15)->withQueryString(),
            'products' => $this->campaigns->productsQuery($filters)->paginate(30)->withQueryString(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'configured' => app(\App\Services\Ai\AiConfigurationResolver::class)->contentConfigured(),
            'researchEnabled' => (bool) Setting::get('ai_product_research_enabled', false),
            'contactFooterConfigured' => $this->footer->isConfigured(),
            'maxItems' => min(500, max(1, (int) Setting::get('ai_product_campaign_max_items', 100))),
            'activeFilters' => $filters,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        return response()->json($this->campaigns->productsQuery($this->filters($request))->paginate(30));
    }

    public function store(Request $request): JsonResponse
    {
        $maxItems = min(500, max(1, (int) Setting::get('ai_product_campaign_max_items', 100)));
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'selected_product_ids' => ['required', 'array', 'min:1', 'max:'.$maxItems],
            'selected_product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'filters' => 'nullable|array',
            'mode' => ['required', Rule::in(['draft', 'publish'])],
            'technical_heading' => ['required', Rule::in(['auto', 'configuration', 'specifications'])],
            'use_web_research' => 'boolean',
            'append_contact_footer' => 'boolean',
            'scheduled_at' => 'required|date',
        ]);
        if (($data['append_contact_footer'] ?? false) && ! $this->footer->isConfigured()) {
            return response()->json(['message' => 'Footer liên hệ chưa đủ cấu hình. Hãy lưu tiêu đề, địa chỉ, số điện thoại, website và Maps trước.'], 422);
        }

        $productIds = array_values(array_unique(array_map('intval', $data['selected_product_ids'])));
        $products = Product::with(['category.parent', 'brand', 'specifications.specificationKey'])->whereIn('id', $productIds)->get()->keyBy('id');
        if ($products->count() !== count($productIds)) {
            return response()->json(['message' => 'Một số sản phẩm không còn tồn tại. Hãy tải lại danh sách.'], 422);
        }

        $campaign = DB::transaction(function () use ($data, $productIds, $products, $request) {
            $campaign = AiProductContentCampaign::create([
                'name' => $data['name'],
                'filters' => array_merge($data['filters'] ?? [], ['selected_product_ids' => $productIds]),
                'mode' => $data['mode'],
                'technical_heading' => $data['technical_heading'],
                'use_web_research' => (bool) ($data['use_web_research'] ?? false),
                'append_contact_footer' => (bool) ($data['append_contact_footer'] ?? false),
                'max_items' => count($productIds),
                'status' => 'pending',
                'scheduled_at' => Carbon::parse($data['scheduled_at']),
                'created_by' => $request->user()?->id,
            ]);
            foreach ($productIds as $productId) {
                $campaign->items()->create([
                    'product_id' => $productId,
                    'source_snapshot' => $this->campaigns->snapshot($products->get($productId)),
                    'status' => 'pending',
                ]);
            }
            $campaign->refreshProgress();

            return $campaign;
        });

        return response()->json(['success' => true, 'campaign' => $campaign], 201);
    }

    public function show(AiProductContentCampaign $campaign)
    {
        $campaign->load(['creator:id,name', 'items.product:id,name,sku,slug,description,short_description,meta_title,meta_description', 'items.product.category:id,name', 'items.product.brand:id,name']);

        return Inertia::render('Admin/AiProductCampaigns/Show', ['campaign' => $campaign]);
    }

    public function run(AiProductContentCampaign $campaign): JsonResponse
    {
        abort_if($campaign->status === 'cancelled', 409, 'Campaign đã bị hủy.');
        $campaign->update(['scheduled_at' => now(), 'status' => 'pending', 'completed_at' => null]);
        $queued = $this->campaigns->dispatch($campaign->fresh());

        return response()->json(['success' => true, 'queued' => $queued]);
    }

    public function cancel(AiProductContentCampaign $campaign): JsonResponse
    {
        if ($campaign->status === 'completed') {
            return response()->json(['message' => 'Campaign đã hoàn tất.'], 409);
        }
        $campaign->update(['status' => 'cancelled', 'completed_at' => now()]);
        $campaign->items()->whereIn('status', ['pending', 'processing'])->update(['status' => 'skipped', 'error_message' => 'Đã hủy bởi quản trị viên.']);
        $campaign->refreshProgress();

        return response()->json(['success' => true]);
    }

    public function apply(AiProductContentCampaignItem $item): JsonResponse
    {
        abort_unless($item->campaign && $item->campaign->status !== 'cancelled', 409, 'Campaign đã bị hủy.');
        try {
            $this->campaigns->applyItem($item);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['success' => true]);
    }

    public function applyMany(Request $request, AiProductContentCampaign $campaign): JsonResponse
    {
        $data = $request->validate(['item_ids' => 'required|array|min:1', 'item_ids.*' => 'integer']);
        $items = $campaign->items()->whereIn('id', $data['item_ids'])->whereIn('status', ['draft', 'needs_review'])->get();
        $errors = [];
        foreach ($items as $item) {
            try {
                $this->campaigns->applyItem($item);
            } catch (\Throwable $exception) {
                $errors[$item->id] = $exception->getMessage();
            }
        }

        return response()->json(['success' => $errors === [], 'errors' => $errors]);
    }

    public function retry(AiProductContentCampaignItem $item): JsonResponse
    {
        abort_if($item->status !== 'failed', 409, 'Chỉ item lỗi mới được retry.');
        abort_if($item->attempts >= 3, 409, 'Item đã đạt tối đa 3 lần thử.');
        $item->update(['status' => 'pending', 'error_message' => null, 'locked_at' => null]);
        $item->campaign->update(['status' => 'pending', 'completed_at' => null, 'scheduled_at' => now()]);
        $queued = $this->campaigns->dispatch($item->campaign->fresh());

        return response()->json(['success' => true, 'queued' => $queued]);
    }

    /** @return array<string,mixed> */
    private function filters(Request $request): array
    {
        return [
            'search' => $request->string('search')->trim()->toString(),
            'category_id' => $request->integer('category_id') ?: null,
            'brand_id' => $request->integer('brand_id') ?: null,
            'status' => $request->input('status', 'all'),
            'missing_description' => $request->boolean('missing_description'),
            'missing_specifications' => $request->boolean('missing_specifications'),
        ];
    }
}
