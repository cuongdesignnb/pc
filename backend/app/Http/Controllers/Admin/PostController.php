<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $query = Post::with(['author', 'category'])->latest();

        if ($request->search) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->category) {
            $query->where('post_category_id', $request->category);
        }

        $posts = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Posts/Index', [
            'posts' => $posts,
            'filters' => $request->only(['search', 'status', 'category']),
            'categories' => PostCategory::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Posts/Create', [
            'categories' => PostCategory::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:posts',
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string',
            'featured_image' => 'nullable|string',
            'post_category_id' => 'nullable|exists:post_categories,id',
            'status' => 'required|in:draft,published,archived',
            'is_featured' => 'boolean',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id() ?? 1;
        $validated['view_count'] = 0;

        Post::create($validated);

        return redirect()->route('admin.posts.index')
            ->with('success', 'Tạo bài viết thành công');
    }

    public function edit(Post $post)
    {
        $post->load('category');
        
        return Inertia::render('Admin/Posts/Edit', [
            'post' => $post,
            'categories' => PostCategory::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Post $post)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:posts,slug,' . $post->id,
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string',
            'featured_image' => 'nullable|string',
            'post_category_id' => 'nullable|exists:post_categories,id',
            'status' => 'required|in:draft,published,archived',
            'is_featured' => 'boolean',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
        ]);

        $post->update($validated);

        return redirect()->route('admin.posts.index')
            ->with('success', 'Cập nhật bài viết thành công');
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return redirect()->route('admin.posts.index')
            ->with('success', 'Xóa bài viết thành công');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Post::with(['author', 'category'])->latest();

        if ($request->search) {
            $query->where('title', 'like', "%{$request->search}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $posts = $query->get();
        $filename = 'bai-viet-' . date('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($posts) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID', 'Tiêu đề', 'Slug', 'Danh mục', 'Tóm tắt',
                'Trạng thái', 'Nổi bật', 'Lượt xem', 'Ngày đăng',
                'Ảnh đại diện', 'Meta Title', 'Meta Description', 'Nội dung',
            ]);
            foreach ($posts as $p) {
                fputcsv($handle, [
                    $p->id, $p->title, $p->slug,
                    $p->category?->name,
                    $p->excerpt, $p->status,
                    $p->is_featured ? '1' : '0',
                    $p->view_count,
                    $p->published_at?->format('Y-m-d H:i'),
                    $p->featured_image,
                    $p->meta_title, $p->meta_description,
                    $p->body,
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), 'r');

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $header = fgetcsv($handle);
        if (!$header || count($header) < 3) {
            fclose($handle);
            return back()->with('error', 'File CSV không đúng định dạng');
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count($row) < 3) continue;

            try {
                $id = trim($row[0] ?? '');
                $title = trim($row[1] ?? '');
                $slug = trim($row[2] ?? '') ?: Str::slug($title);

                if (!$title) continue;

                $categoryName = trim($row[3] ?? '');
                $category = $categoryName ? PostCategory::where('name', $categoryName)->first() : null;

                $data = [
                    'title' => $title,
                    'slug' => $slug,
                    'post_category_id' => $category?->id,
                    'excerpt' => $row[4] ?? null,
                    'status' => in_array($row[5] ?? '', ['published', 'draft', 'archived']) ? $row[5] : 'draft',
                    'is_featured' => boolval($row[6] ?? false),
                    'featured_image' => $row[9] ?? null,
                    'meta_title' => $row[10] ?? null,
                    'meta_description' => $row[11] ?? null,
                    'body' => $row[12] ?? '',
                ];

                if ($id && Post::find($id)) {
                    Post::where('id', $id)->update($data);
                    $updated++;
                } else {
                    $baseSlug = $data['slug'];
                    $counter = 1;
                    while (Post::where('slug', $data['slug'])->exists()) {
                        $data['slug'] = $baseSlug . '-' . $counter++;
                    }
                    $data['user_id'] = Auth::id() ?? 1;
                    $data['view_count'] = 0;
                    $data['published_at'] = ($data['status'] === 'published') ? now() : null;
                    Post::create($data);
                    $created++;
                }
            } catch (\Exception $e) {
                $errors[] = "Dòng {$line}: {$e->getMessage()}";
            }
        }
        fclose($handle);

        $msg = "Import xong: {$created} bài viết mới, {$updated} cập nhật.";
        if (count($errors) > 0) {
            $msg .= ' Lỗi: ' . implode('; ', array_slice($errors, 0, 5));
        }

        return back()->with('success', $msg);
    }
}
