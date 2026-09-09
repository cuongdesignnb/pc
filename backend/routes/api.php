<?php

use App\Http\Controllers\Admin\AiGenerationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutQuoteController;
use App\Http\Controllers\Api\HomepageController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PcBuilderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductQuestionController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Auth. The auth namespace is the single public contract used by the
    // storefront. Keep the old endpoints below for older clients while they
    // migrate to /auth/*.
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    });
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // Public routes
    // Aggregated storefront homepage payload
    Route::get('/homepage', [HomepageController::class, 'index']);

    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::get('/products/{slug}/suggestions', [ProductController::class, 'suggestions']);
    Route::get('/products/{slug}/compatibility-summary', [ProductController::class, 'compatibilitySummary']);
    Route::get('/products/{slug}/relations', [ProductController::class, 'relations']);
    Route::get('/products/{slug}/reviews', [ReviewController::class, 'index']);
    Route::post('/products/{slug}/reviews', [ReviewController::class, 'store']);
    Route::get('/products/{slug}/questions', [ProductQuestionController::class, 'index']);
    Route::post('/products/{slug}/questions', [ProductQuestionController::class, 'store']);
    Route::post('/products/cards', [ProductController::class, 'cards']);
    Route::get('/products/component/{slug}', [ProductController::class, 'byComponentType']);

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/homepage-sections', [CategoryController::class, 'homepageSections']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);

    // Brands
    Route::get('/brands', [BrandController::class, 'index']);

    // Menus
    Route::get('/menus/{location}', [MenuController::class, 'byLocation']);

    // Banners
    Route::get('/banners', [BannerController::class, 'index']);

    // Settings
    Route::get('/settings', [SettingController::class, 'index']);

    // Newsletter
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);

    // Search
    Route::get('/search', [SearchController::class, 'index']);

    // Locations
    Route::get('/locations/provinces', [LocationController::class, 'provinces']);
    Route::get('/locations/provinces/{code}/wards', [LocationController::class, 'wards']);

    // Blog
    // Keep the landing endpoint before /blog/{slug} so "home" is not treated as a slug.
    Route::get('/blog/home', [BlogController::class, 'home']);
    Route::get('/blog', [BlogController::class, 'index']);
    Route::get('/blog/categories', [BlogController::class, 'categories']);
    Route::get('/blog/featured', [BlogController::class, 'featured']);
    Route::get('/blog/{slug}', [BlogController::class, 'show']);
    Route::post('/blog/{slug}/view', [BlogController::class, 'view'])->middleware('throttle:30,1');

    // PC Builder
    Route::get('/builder/component-types', [PcBuilderController::class, 'componentTypes']);
    Route::post('/builder/compatible/{slug}', [PcBuilderController::class, 'compatibleProducts']);
    Route::post('/builder/check', [PcBuilderController::class, 'checkBuild']);
    Route::get('/builder/presets', [PcBuilderController::class, 'presets']);
    Route::get('/builder/presets/{slug}', [PcBuilderController::class, 'preset']);

    // Cart (works with session or auth)
    Route::get('/cart', [CartController::class, 'index']);
    Route::get('/cart/recommendations', [CartController::class, 'recommendations']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/selection', [CartController::class, 'selectAll']);
    Route::delete('/cart/items', [CartController::class, 'removeItems']);
    Route::patch('/cart/items/{cartItem}/selection', [CartController::class, 'updateSelection']);
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'removeItem']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Checkout quote and orders (public for guest checkout)
    Route::post('/checkout/quote', [CheckoutQuoteController::class, 'store']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/check-payment', [OrderController::class, 'checkPayment']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    // Sepay webhook
    Route::post('/sepay/callback', [OrderController::class, 'sepayCallback']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/commerce/merge', [AuthController::class, 'mergeCommerce']);
        });

        // Legacy aliases retained for existing clients.
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/user/profile', [AuthController::class, 'updateProfile']);
        Route::put('/user/password', [AuthController::class, 'changePassword']);

        // Wishlist is server-backed for authenticated users. Guest wishlist
        // state remains in the storefront until a successful merge.
        Route::get('/wishlist', [\App\Http\Controllers\Api\WishlistController::class, 'index']);
        Route::post('/wishlist/items', [\App\Http\Controllers\Api\WishlistController::class, 'store']);
        Route::delete('/wishlist/items/{product}', [\App\Http\Controllers\Api\WishlistController::class, 'destroy']);
        Route::post('/wishlist/merge', [\App\Http\Controllers\Api\WishlistController::class, 'merge']);

        // Saved builds (requires auth)
        Route::post('/builder/save', [PcBuilderController::class, 'saveBuild']);
        Route::get('/builder/saved', [PcBuilderController::class, 'savedBuilds']);
        Route::get('/builder/saved/{savedBuild}', [PcBuilderController::class, 'savedBuild']);
        Route::delete('/builder/saved/{savedBuild}', [PcBuilderController::class, 'destroySavedBuild']);

        // Customer account dashboard and owned commerce data.
        Route::get('/account/dashboard', [AccountController::class, 'dashboard']);
        Route::get('/account/orders', [AccountController::class, 'orders']);
        Route::get('/account/orders/{order}', [AccountController::class, 'order']);
        Route::get('/account/saved-builds', [AccountController::class, 'savedBuilds']);
        Route::get('/account/addresses', [AccountController::class, 'addresses']);
        Route::post('/account/addresses', [AccountController::class, 'storeAddress']);
        Route::patch('/account/addresses/{address}', [AccountController::class, 'updateAddress']);
        Route::delete('/account/addresses/{address}', [AccountController::class, 'destroyAddress']);
        Route::get('/account/warranties', [AccountController::class, 'warranties']);

        // User orders
        Route::get('/orders', [OrderController::class, 'index']);
    });
});

// Admin-only AI writing endpoints. The web middleware keeps the existing admin session;
// API keys never leave the server.
Route::prefix('ai')->middleware(['web', 'admin.auth', 'permission:ai-articles.create|posts.create|products.create|products.edit', 'throttle:ai-generation'])->group(function () {
    Route::post('/content', [AiGenerationController::class, 'content'])->name('api.ai.content');
    Route::post('/content-with-images', [AiGenerationController::class, 'contentWithImages'])->name('api.ai.content-with-images');
});
