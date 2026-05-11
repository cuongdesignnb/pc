<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    /**
     * Get all posts
     */
    public function index(Request $request)
    {
        $query = Post::with(['category', 'author'])
            ->where('status', 'published')
            ->where('published_at', '<=', now());

        // Filter by category
        if ($request->has('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Search
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                    ->orWhere('excerpt', 'like', "%{$request->search}%");
            });
        }

        $posts = $query->orderBy('published_at', 'desc')
            ->paginate(12);

        return response()->json([
            'posts' => $posts->items(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Get single post
     */
    public function show(string $slug)
    {
        $post = Post::with(['category', 'author'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        // Increment view count
        $post->increment('view_count');

        // Related posts
        $related = Post::with(['category'])
            ->where('status', 'published')
            ->where('id', '!=', $post->id)
            ->where('post_category_id', $post->post_category_id)
            ->limit(4)
            ->get();

        return response()->json([
            'post' => $post,
            'related' => $related,
        ]);
    }

    /**
     * Get post categories
     */
    public function categories()
    {
        $categories = PostCategory::withCount(['posts' => function ($query) {
            $query->where('status', 'published');
        }])->get();

        return response()->json($categories);
    }

    /**
     * Get featured posts
     */
    public function featured()
    {
        $posts = Post::with(['category', 'author'])
            ->where('status', 'published')
            ->where('is_featured', true)
            ->orderBy('published_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json($posts);
    }
}
