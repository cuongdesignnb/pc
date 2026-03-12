<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PcBuilderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Public routes
    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::get('/products/{slug}/suggestions', [ProductController::class, 'suggestions']);
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

    // Locations
    Route::get('/locations/provinces', [LocationController::class, 'provinces']);
    Route::get('/locations/provinces/{code}/wards', [LocationController::class, 'wards']);

    // Blog
    Route::get('/blog', [BlogController::class, 'index']);
    Route::get('/blog/categories', [BlogController::class, 'categories']);
    Route::get('/blog/featured', [BlogController::class, 'featured']);
    Route::get('/blog/{slug}', [BlogController::class, 'show']);

    // PC Builder
    Route::get('/builder/component-types', [PcBuilderController::class, 'componentTypes']);
    Route::post('/builder/compatible/{slug}', [PcBuilderController::class, 'compatibleProducts']);
    Route::post('/builder/check', [PcBuilderController::class, 'checkBuild']);

    // Cart (works with session or auth)
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'removeItem']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Orders (public for guest checkout)
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/check-payment', [OrderController::class, 'checkPayment']);
    
    // Sepay webhook
    Route::post('/sepay/callback', [OrderController::class, 'sepayCallback']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/user/profile', [AuthController::class, 'updateProfile']);
        Route::put('/user/password', [AuthController::class, 'changePassword']);

        // Saved builds (requires auth)
        Route::post('/builder/save', [PcBuilderController::class, 'saveBuild']);
        Route::get('/builder/saved', [PcBuilderController::class, 'savedBuilds']);

        // User orders
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    });
});

