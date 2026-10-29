<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreActivity;
use App\Support\StoreActivityVideo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StoreActivityController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/StoreActivities/Index', [
            'activities' => StoreActivity::orderBy('sort_order')->orderByDesc('id')->get()
                ->map(fn (StoreActivity $activity) => $activity->toArray() + ['preview' => $activity->toStorefront()]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/StoreActivities/Form', ['activity' => null]);
    }

    public function store(Request $request)
    {
        StoreActivity::create($this->validated($request));

        return redirect()->route('admin.store-activities.index')
            ->with('success', 'Đã thêm hoạt động cửa hàng');
    }

    public function edit(StoreActivity $storeActivity)
    {
        return Inertia::render('Admin/StoreActivities/Form', ['activity' => $storeActivity]);
    }

    public function update(Request $request, StoreActivity $storeActivity)
    {
        $storeActivity->update($this->validated($request));

        return redirect()->route('admin.store-activities.index')
            ->with('success', 'Đã cập nhật hoạt động cửa hàng');
    }

    public function destroy(StoreActivity $storeActivity)
    {
        $storeActivity->delete();

        return redirect()->route('admin.store-activities.index')
            ->with('success', 'Đã xóa hoạt động cửa hàng');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => ['required', Rule::in(StoreActivity::TYPES)],
            'image' => 'nullable|string|max:500|required_if:type,image',
            'video_url' => [
                'nullable', 'string', 'max:500', 'required_if:type,video',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($request->input('type') === 'video' && filled($value) && StoreActivityVideo::resolve((string) $value) === null) {
                        $fail('Video phải là link YouTube, link video Facebook hoặc file .mp4/.webm (HTTPS hoặc từ thư viện Media).');
                    }
                },
            ],
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ], [
            'image.required_if' => 'Chọn ảnh từ thư viện Media.',
            'video_url.required_if' => 'Nhập link video.',
        ]);

        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        if ($validated['type'] === 'image') {
            $validated['video_url'] = null;
        }

        return $validated;
    }
}
