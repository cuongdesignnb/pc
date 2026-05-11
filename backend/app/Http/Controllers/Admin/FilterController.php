<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Filter;
use App\Models\FilterValue;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FilterController extends Controller
{
    public function index()
    {
        $filters = Filter::withCount('values', 'categories')
            ->with(['values' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/Filters/Index', [
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Filters/Form', [
            'filter' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:filters',
            'type' => 'required|in:checkbox,price_range',
            'match_field' => 'required|in:specifications_text,product_name,brand,price',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'values' => 'nullable|array',
            'values.*.label' => 'required|string|max:255',
            'values.*.slug' => 'required|string|max:255',
            'values.*.match_value' => 'nullable|string|max:500',
            'values.*.price_min' => 'nullable|numeric|min:0',
            'values.*.price_max' => 'nullable|numeric|min:0',
            'values.*.sort_order' => 'integer|min:0',
            'values.*.is_active' => 'boolean',
        ]);

        $filter = Filter::create(collect($validated)->except('values')->toArray());

        // Create values
        if (!empty($validated['values'])) {
            foreach ($validated['values'] as $val) {
                $filter->values()->create($val);
            }
        }

        return redirect()->route('admin.filters.index')
            ->with('success', 'Tạo bộ lọc thành công');
    }

    public function edit(Filter $filter)
    {
        $filter->load(['values' => fn ($q) => $q->orderBy('sort_order')]);

        return Inertia::render('Admin/Filters/Form', [
            'filter' => $filter,
        ]);
    }

    public function update(Request $request, Filter $filter)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:filters,slug,' . $filter->id,
            'type' => 'required|in:checkbox,price_range',
            'match_field' => 'required|in:specifications_text,product_name,brand,price',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'values' => 'nullable|array',
            'values.*.id' => 'nullable|integer',
            'values.*.label' => 'required|string|max:255',
            'values.*.slug' => 'required|string|max:255',
            'values.*.match_value' => 'nullable|string|max:500',
            'values.*.price_min' => 'nullable|numeric|min:0',
            'values.*.price_max' => 'nullable|numeric|min:0',
            'values.*.sort_order' => 'integer|min:0',
            'values.*.is_active' => 'boolean',
        ]);

        $filter->update(collect($validated)->except('values')->toArray());

        // Sync values: delete removed, update existing, create new
        $incomingIds = collect($validated['values'] ?? [])
            ->pluck('id')
            ->filter()
            ->toArray();

        // Delete values not in the incoming list
        $filter->values()->whereNotIn('id', $incomingIds)->delete();

        // Update or create values
        foreach ($validated['values'] ?? [] as $index => $val) {
            $val['sort_order'] = $val['sort_order'] ?? $index;
            if (!empty($val['id'])) {
                FilterValue::where('id', $val['id'])
                    ->where('filter_id', $filter->id)
                    ->update(collect($val)->except('id')->toArray());
            } else {
                $filter->values()->create(collect($val)->except('id')->toArray());
            }
        }

        return redirect()->route('admin.filters.index')
            ->with('success', 'Cập nhật bộ lọc thành công');
    }

    public function destroy(Filter $filter)
    {
        $filter->delete();

        return redirect()->route('admin.filters.index')
            ->with('success', 'Xóa bộ lọc thành công');
    }
}
