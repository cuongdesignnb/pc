<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompatibilityRule;
use App\Models\ComponentType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompatibilityController extends Controller
{
    public function index(Request $request)
    {
        $query = CompatibilityRule::with(['sourceType', 'targetType'])->latest();

        if ($request->filled('source_type_id')) {
            $query->where('source_type_id', $request->source_type_id);
        }

        $rules = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Compatibility/Index', [
            'rules' => $rules,
            'componentTypes' => ComponentType::orderBy('display_order')->get(),
            'filters' => $request->only(['source_type_id']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Compatibility/Create', [
            'componentTypes' => ComponentType::with(['specificationKeys' => fn ($q) => $q->orderBy('display_order')])->orderBy('display_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'source_type_id' => 'required|exists:component_types,id',
            'target_type_id' => 'required|exists:component_types,id',
            'source_spec_key' => 'required|string|max:255',
            'target_spec_key' => 'required|string|max:255',
            'rule_type' => 'required|in:must_match,must_fit,must_fit_dimension,must_contain,power_check',
            'allowed_values' => 'nullable|array',
            'power_headroom' => 'nullable|integer',
            'message' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        CompatibilityRule::create($validated);

        return redirect()->route('admin.compatibility.index')
            ->with('success', 'Tạo quy tắc tương thích thành công');
    }

    public function edit(CompatibilityRule $compatibility)
    {
        $compatibility->load(['sourceType', 'targetType']);

        return Inertia::render('Admin/Compatibility/Edit', [
            'rule' => $compatibility,
            'componentTypes' => ComponentType::with(['specificationKeys' => fn ($q) => $q->orderBy('display_order')])->orderBy('display_order')->get(),
        ]);
    }

    public function update(Request $request, CompatibilityRule $compatibility)
    {
        $validated = $request->validate([
            'source_type_id' => 'required|exists:component_types,id',
            'target_type_id' => 'required|exists:component_types,id',
            'source_spec_key' => 'required|string|max:255',
            'target_spec_key' => 'required|string|max:255',
            'rule_type' => 'required|in:must_match,must_fit,must_fit_dimension,must_contain,power_check',
            'allowed_values' => 'nullable|array',
            'power_headroom' => 'nullable|integer',
            'message' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $compatibility->update($validated);

        return redirect()->route('admin.compatibility.index')
            ->with('success', 'Cập nhật quy tắc thành công');
    }

    public function destroy(CompatibilityRule $compatibility)
    {
        $compatibility->delete();

        return redirect()->route('admin.compatibility.index')
            ->with('success', 'Xóa quy tắc thành công');
    }
}
