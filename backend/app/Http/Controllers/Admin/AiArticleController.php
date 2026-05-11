<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiArticleBatch;
use App\Models\AiArticleBatchItem;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Setting;
use App\Services\AiArticleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AiArticleController extends Controller
{
    /**
     * List all batches.
     */
    public function index()
    {
        $batches = AiArticleBatch::with('category')
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/AiArticles/Index', [
            'batches' => $batches,
        ]);
    }

    /**
     * Create batch form.
     */
    public function create()
    {
        return Inertia::render('Admin/AiArticles/Create', [
            'categories' => PostCategory::orderBy('sort_order')->get(['id', 'name']),
            'hasKeys' => [
                'chatgpt' => !empty(Setting::get('chatgpt_api_key')),
                'gemini' => !empty(Setting::get('gemini_api_key')),
            ],
        ]);
    }

    /**
     * Store a new batch.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'keywords' => 'required|string',
            'ai_provider' => 'required|in:chatgpt,gemini',
            'default_category_id' => 'nullable|exists:post_categories,id',
            'schedule_at' => 'nullable|date',
            'auto_publish' => 'boolean',
        ]);

        $keywords = array_filter(
            array_map('trim', explode("\n", $validated['keywords']))
        );

        if (empty($keywords)) {
            return back()->with('error', 'Danh sach tu khoa khong duoc de trong.');
        }

        $batch = AiArticleBatch::create([
            'name' => $validated['name'],
            'keywords' => $keywords,
            'status' => 'pending',
            'ai_provider' => $validated['ai_provider'],
            'default_category_id' => $validated['default_category_id'] ?? null,
            'schedule_at' => $validated['schedule_at'] ?? null,
            'auto_publish' => $validated['auto_publish'] ?? false,
            'total_items' => count($keywords),
            'completed_items' => 0,
        ]);

        // Create individual items
        foreach ($keywords as $keyword) {
            AiArticleBatchItem::create([
                'batch_id' => $batch->id,
                'keyword' => $keyword,
                'status' => 'pending',
            ]);
        }

        return redirect()->route('admin.ai-articles.index')
            ->with('success', "Da tao batch \"{$batch->name}\" voi " . count($keywords) . " tu khoa.");
    }

    /**
     * Show batch details.
     */
    public function show(AiArticleBatch $aiArticle)
    {
        $aiArticle->load(['items.post', 'category']);

        return Inertia::render('Admin/AiArticles/Show', [
            'batch' => $aiArticle,
        ]);
    }

    /**
     * Run/process a batch.
     */
    public function run(AiArticleBatch $aiArticle)
    {
        if ($aiArticle->status === 'processing') {
            return back()->with('error', 'Batch dang duoc xu ly.');
        }

        $aiArticle->update(['status' => 'processing']);

        $service = new AiArticleService();
        $completed = 0;

        foreach ($aiArticle->items()->where('status', 'pending')->get() as $item) {
            try {
                $item->update(['status' => 'generating']);

                // Generate article
                $article = $service->generateArticle(
                    $item->keyword,
                    $aiArticle->ai_provider
                );

                // Generate featured image
                $imageUrl = null;
                if (!empty($article['image_prompt'])) {
                    $imageProvider = !empty(Setting::get('gemini_api_key')) ? 'gemini' : 'chatgpt';
                    $imageUrl = $service->generateImage($article['image_prompt'], $imageProvider);
                }

                // Ensure unique slug
                $slug = $article['slug'];
                $counter = 1;
                while (Post::where('slug', $slug)->exists()) {
                    $slug = $article['slug'] . '-' . $counter++;
                }

                // Create post
                $post = Post::create([
                    'user_id' => Auth::id() ?? 1,
                    'post_category_id' => $aiArticle->default_category_id,
                    'title' => $article['title'],
                    'slug' => $slug,
                    'excerpt' => $article['excerpt'],
                    'body' => $article['body'],
                    'featured_image' => $imageUrl,
                    'status' => $aiArticle->auto_publish ? 'published' : 'draft',
                    'is_featured' => false,
                    'published_at' => $aiArticle->auto_publish ? now() : null,
                    'meta_title' => $article['meta_title'],
                    'meta_description' => $article['meta_description'],
                    'view_count' => 0,
                ]);

                $item->update([
                    'status' => 'completed',
                    'post_id' => $post->id,
                    'generated_at' => now(),
                ]);

                $completed++;
                $aiArticle->update(['completed_items' => $completed]);
            } catch (\Exception $e) {
                $item->update([
                    'status' => 'failed',
                    'error_message' => Str::limit($e->getMessage(), 500),
                ]);
            }
        }

        $allCompleted = $aiArticle->items()->where('status', '!=', 'completed')->where('status', '!=', 'failed')->count() === 0;
        $aiArticle->update([
            'status' => $allCompleted ? 'completed' : 'failed',
            'completed_items' => $aiArticle->items()->where('status', 'completed')->count(),
        ]);

        return back()->with('success', "Hoan thanh {$completed}/{$aiArticle->total_items} bai viet.");
    }

    /**
     * Generate single article (AJAX from Post Create/Edit).
     */
    public function generateSingle(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|max:255',
            'provider' => 'required|in:chatgpt,gemini',
        ]);

        try {
            $service = new AiArticleService();
            $article = $service->generateArticle($request->keyword, $request->provider);

            // Generate image
            $imageUrl = null;
            if (!empty($article['image_prompt'])) {
                $imageProvider = !empty(Setting::get('gemini_api_key')) ? 'gemini' : 'chatgpt';
                $imageUrl = $service->generateImage($article['image_prompt'], $imageProvider);
            }
            $article['featured_image'] = $imageUrl;

            return response()->json(['success' => true, 'article' => $article]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete batch.
     */
    public function destroy(AiArticleBatch $aiArticle)
    {
        $aiArticle->delete();

        return redirect()->route('admin.ai-articles.index')
            ->with('success', 'Da xoa batch thanh cong.');
    }
}
