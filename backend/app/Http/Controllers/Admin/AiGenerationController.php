<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AiProviderException;
use App\Http\Controllers\Controller;
use App\Models\AiGenerationSchedule;
use App\Models\PostCategory;
use App\Models\Product;
use App\Services\Ai\AiConfigurationResolver;
use App\Services\Ai\AiGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AiGenerationController extends Controller
{
    public function __construct(
        private readonly AiGenerationService $generation,
        private readonly AiConfigurationResolver $configuration,
    ) {}

    public function content(Request $request): JsonResponse
    {
        return $this->generate($request, false);
    }

    public function contentWithImages(Request $request): JsonResponse
    {
        return $this->generate($request, true);
    }

    public function index()
    {
        return Inertia::render('Admin/AiWriter/Index', [
            'schedules' => AiGenerationSchedule::with(['product', 'article'])->latest()->paginate(20),
            'categories' => PostCategory::orderBy('sort_order')->get(['id', 'name']),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku']),
            'configured' => $this->configuration->contentConfigured(),
        ]);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $request->merge([
            'category_id' => $request->filled('category_id') ? $request->input('category_id') : null,
            'product_id' => $request->filled('product_id') ? $request->input('product_id') : null,
            'image_count' => $request->filled('image_count') ? $request->input('image_count') : 0,
        ]);

        $data = $request->validate([
            'topic' => 'required|string|max:500',
            'keywords' => 'nullable|string|max:1000',
            'type' => 'required|in:article,product_description,category_description,seo',
            'tone' => 'required|in:professional,casual,luxury',
            'length' => 'required|in:short,medium,long',
            'full_article' => 'boolean',
            'with_images' => 'boolean',
            'image_count' => 'integer|min:0|max:10',
            'auto_publish' => 'boolean',
            'category_id' => 'nullable|integer|exists:post_categories,id',
            'product_id' => 'nullable|exists:products,id',
            'scheduled_at' => 'required|date',
        ]);

        if ($data['type'] === 'product_description' && empty($data['product_id'])) {
            return response()->json(['success' => false, 'message' => 'Mô tả sản phẩm cần chọn sản phẩm.'], 422);
        }

        try {
            $schedule = DB::transaction(fn () => AiGenerationSchedule::create([
                ...$data,
                'scheduled_at' => Carbon::parse($data['scheduled_at']),
                'created_by' => $request->user()?->id,
                'status' => 'pending',
            ]));
        } catch (\Throwable $e) {
            Log::error('AI schedule could not be saved.', [
                'type' => $data['type'] ?? null,
                'has_product' => filled($data['product_id'] ?? null),
                'has_category' => filled($data['category_id'] ?? null),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error_code' => 'AI_SCHEDULE_SAVE_FAILED',
                'message' => 'Không thể lưu lịch viết AI. Vui lòng kiểm tra cấu hình cơ sở dữ liệu và thử lại.',
            ], 500);
        }

        return response()->json(['success' => true, 'schedule' => $schedule], 201);
    }

    public function cancel(AiGenerationSchedule $schedule): JsonResponse
    {
        if ($schedule->status === 'processing') {
            return response()->json(['success' => false, 'message' => 'Lịch đang được xử lý.'], 409);
        }
        $schedule->update(['status' => 'failed', 'error_message' => 'Đã hủy bởi quản trị viên.', 'completed_at' => now()]);

        return response()->json(['success' => true]);
    }

    private function generate(Request $request, bool $withImages): JsonResponse
    {
        $data = $request->validate([
            'topic' => 'required|string|max:500',
            'type' => 'required|in:article,product_description,category_description,seo',
            'keywords' => 'nullable|string|max:1000',
            'tone' => 'required|in:professional,casual,luxury',
            'length' => 'required|in:short,medium,long',
            'full_article' => 'boolean',
            'category_id' => 'nullable|integer',
            'existing_content' => 'nullable|string|max:20000',
            'product_id' => 'nullable|exists:products,id',
            'image_count' => $withImages ? 'integer|min:0|max:10' : 'nullable',
        ]);
        $data['with_images'] = $withImages;
        $data['image_count'] = $withImages ? (int) ($data['image_count'] ?? 0) : 0;

        try {
            return response()->json(['success' => true, ...$this->generation->generate($data, $request->user()?->id)]);
        } catch (AiProviderException $e) {
            $status = $e->providerStatus && $e->providerStatus >= 400 && $e->providerStatus <= 599 ? $e->providerStatus : 502;

            return response()->json(['success' => false, 'error' => $e->getMessage(), 'message' => $e->getMessage()], $status);
        } catch (\Throwable) {
            return response()->json(['success' => false, 'error' => 'Không thể tạo nội dung AI lúc này.'], 500);
        }
    }
}
