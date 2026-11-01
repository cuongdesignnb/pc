<?php

namespace App\Providers;

use App\Models\Post;
use App\Models\Product;
use App\Services\Seo\IndexNow;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('ai-generation', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        Product::saved(fn (Product $product) => app(IndexNow::class)->productChanged($product));
        Post::saved(fn (Post $post) => app(IndexNow::class)->postChanged($post));
    }
}
