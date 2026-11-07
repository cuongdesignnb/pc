<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** SEO → Chuyển hướng: old URL → new URL rules for the storefront. */
class RedirectController extends Controller
{
    private const RESERVED = ['/', '/api', '/admin', '/_nuxt', '/sitemap.xml', '/robots.txt'];

    public function index(): Response
    {
        return Inertia::render('Admin/Redirects/Index', [
            'redirects' => Redirect::query()->orderBy('source_path')->get()
                ->map(fn (Redirect $redirect) => $redirect->only(['id', 'source_path', 'target', 'status_code', 'is_active', 'note'])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Redirect::create($this->validated($request));

        return back()->with('success', 'Đã thêm chuyển hướng.');
    }

    public function update(Request $request, Redirect $redirect): RedirectResponse
    {
        $redirect->update($this->validated($request, $redirect));

        return back()->with('success', 'Đã lưu chuyển hướng.');
    }

    public function destroy(Redirect $redirect): RedirectResponse
    {
        $redirect->delete();

        return back()->with('success', 'Đã xoá chuyển hướng.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Redirect $editing = null): array
    {
        $data = $request->validate([
            'source_path' => 'required|string|max:500',
            'target' => 'required|string|max:1000',
            'status_code' => 'required|integer|in:301,302',
            'is_active' => 'boolean',
            'note' => 'nullable|string|max:255',
        ]);

        $source = Redirect::normalizePath($data['source_path']);
        if ($source === null || in_array($source, self::RESERVED, true) || str_starts_with($source, '/api/') || str_starts_with($source, '/_nuxt/') || str_starts_with($source, '/admin/')) {
            throw ValidationException::withMessages(['source_path' => 'Đường dẫn cũ phải là một đường dẫn trên web (ví dụ /gioi-thieu), không phải trang chủ, /api hay /admin.']);
        }
        $target = trim($data['target']);
        $localTarget = Redirect::normalizePath($target);
        $isExternal = (bool) preg_match('#^https://#i', $target) && filter_var($target, FILTER_VALIDATE_URL);
        if (! $isExternal && (! str_starts_with($target, '/') || str_starts_with($target, '//'))) {
            throw ValidationException::withMessages(['target' => 'Đường dẫn mới phải bắt đầu bằng / hoặc là link https:// đầy đủ.']);
        }
        if (! $isExternal && $localTarget === $source) {
            throw ValidationException::withMessages(['target' => 'Đường dẫn mới trùng đường dẫn cũ.']);
        }
        if (Redirect::where('source_path', $source)->when($editing, fn ($q) => $q->whereKeyNot($editing->id))->exists()) {
            throw ValidationException::withMessages(['source_path' => 'Đường dẫn cũ này đã có chuyển hướng.']);
        }
        // A → B while B → A (or B → C) would loop or chain.
        if (! $isExternal && Redirect::where('source_path', $localTarget)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['target' => 'Đường dẫn mới đang được chuyển hướng tiếp; hãy trỏ thẳng tới trang đích cuối cùng.']);
        }

        return [
            'source_path' => $source,
            'target' => $target,
            'status_code' => (int) $data['status_code'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'note' => $data['note'] ?? null,
        ];
    }
}
