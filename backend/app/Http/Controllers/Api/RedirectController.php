<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RedirectController extends Controller
{
    /** Active rules for the storefront, which caches them for a minute. */
    public function index(): JsonResponse
    {
        $rules = Cache::remember(Redirect::CACHE_KEY, 300, fn () => Redirect::query()
            ->where('is_active', true)
            ->get(['source_path', 'target', 'status_code'])
            ->map(fn (Redirect $redirect) => ['from' => $redirect->source_path, 'to' => $redirect->target, 'status' => $redirect->status_code])
            ->values()
            ->all());

        return response()->json(['data' => $rules])->setSharedMaxAge(60);
    }
}
