<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminApi\AdminApiTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    public function __construct(private readonly AdminApiTokens $tokens) {}

    public function index(): Response
    {
        return Inertia::render('Admin/ApiTokens/Index', [
            'tokens' => $this->tokens->all(),
            'types' => collect(AdminApiTokens::TYPES)->map(fn (array $type, string $key) => ['value' => $key, 'label' => $type['label']])->values(),
            'apiBase' => url('/api/v1/admin-api'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[^:]+$/'],
            'type' => ['required', Rule::in(array_keys(AdminApiTokens::TYPES))],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ], ['name.regex' => 'Tên token không được chứa dấu hai chấm.']);

        if (! $this->tokens->mayIssue($request->user(), $validated['type'])) {
            return back()->withErrors(['type' => 'Bạn cần quyền xem đơn hàng để tạo token Đơn hàng.']);
        }

        $token = $this->tokens->issue($request->user(), $validated['name'], $validated['type'], $validated['expires_in_days'] ?? null);

        // Shown once: only a hash is stored.
        return back()->with('api_token', $token->plainTextToken)
            ->with('success', 'Đã tạo token "'.$validated['name'].'". Hãy sao chép ngay, token chỉ hiện một lần.');
    }

    public function destroy(int $token): RedirectResponse
    {
        return $this->tokens->revoke($token)
            ? back()->with('success', 'Đã thu hồi token. Công cụ dùng token này sẽ bị từ chối ngay.')
            : back()->with('error', 'Không tìm thấy token.');
    }
}
