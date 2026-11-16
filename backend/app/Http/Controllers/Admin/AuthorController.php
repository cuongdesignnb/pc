<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Post;
use App\Services\Seo\VietnameseSlugNormalizer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Nội dung → Tác giả: bylines picked in the post form. */
class AuthorController extends Controller
{
    public function index(Request $request)
    {
        $authors = Author::query()
            ->withCount('posts')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Authors/Index', [
            'authors' => $authors,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Authors/Form', ['author' => null, 'socialKeys' => Author::SOCIAL_KEYS]);
    }

    public function store(Request $request, VietnameseSlugNormalizer $slugs)
    {
        $validated = $this->validated($request, $slugs);
        if ($validated instanceof \Illuminate\Http\RedirectResponse) {
            return $validated;
        }
        Author::create($validated);

        return redirect()->route('admin.authors.index')->with('success', 'Đã thêm tác giả');
    }

    public function edit(Author $author)
    {
        return Inertia::render('Admin/Authors/Form', ['author' => $author, 'socialKeys' => Author::SOCIAL_KEYS]);
    }

    public function update(Request $request, Author $author, VietnameseSlugNormalizer $slugs)
    {
        $validated = $this->validated($request, $slugs, $author);
        if ($validated instanceof \Illuminate\Http\RedirectResponse) {
            return $validated;
        }
        $author->update($validated);

        return redirect()->route('admin.authors.index')->with('success', 'Đã cập nhật tác giả');
    }

    public function destroy(Author $author)
    {
        // Posts fall back to showing the admin user who wrote them.
        Post::query()->where('author_id', $author->id)->update(['author_id' => null]);
        $author->delete();

        return redirect()->route('admin.authors.index')->with('success', 'Đã xóa tác giả');
    }

    /** @return array<string, mixed>|\Illuminate\Http\RedirectResponse */
    private function validated(Request $request, VietnameseSlugNormalizer $slugs, ?Author $author = null)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('authors', 'slug')->ignore($author?->id)],
            'avatar' => 'nullable|string|max:500',
            'job_title' => 'nullable|string|max:150',
            'bio' => 'nullable|string|max:5000',
            'social_links' => 'nullable|array',
            'social_links.*' => 'nullable|url:https|max:500',
            'is_active' => 'boolean',
        ], [
            'social_links.*.url' => 'Link mạng xã hội phải bắt đầu bằng https://.',
        ]);

        try {
            $slug = filled($validated['slug'] ?? null)
                ? $slugs->validateCustom((string) $validated['slug'])
                : $slugs->normalize($validated['name']);
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['slug' => $exception->getMessage()])->withInput();
        }
        if (Author::query()->where('slug', $slug)->when($author, fn ($query) => $query->whereKeyNot($author->id))->exists()) {
            return back()->withErrors(['slug' => 'Slug này đã được dùng cho tác giả khác.'])->withInput();
        }
        $validated['slug'] = $slug;
        $validated['social_links'] = collect($validated['social_links'] ?? [])
            ->only(Author::SOCIAL_KEYS)->filter(fn ($url) => filled($url))->all() ?: null;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        return $validated;
    }
}
