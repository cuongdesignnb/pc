<?php

use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CompatibilityController;
use App\Http\Controllers\Admin\ComponentTypeController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PostCategoryController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ─── Payment routes (SePay Gateway) ─────────────────────────────────────────
Route::prefix('payment')->name('payment.')->group(function () {
    // Checkout redirect to SePay
    Route::get('/checkout/{invoice}', [PaymentController::class, 'checkout'])->name('checkout');

    // Callback URLs from SePay after payment
    Route::get('/success', [PaymentController::class, 'success'])->name('success');
    Route::get('/error', [PaymentController::class, 'error'])->name('error');
    Route::get('/cancel', [PaymentController::class, 'cancel'])->name('cancel');

    // IPN endpoint (POST from SePay server)
    Route::post('/ipn', [PaymentController::class, 'ipn'])->name('ipn');
});

// Admin routes
Route::prefix('admin')->middleware(['web'])->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Products
    Route::resource('products', ProductController::class);

    // Categories
    Route::resource('categories', CategoryController::class);

    // Brands
    Route::resource('brands', BrandController::class);

    // Orders
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::patch('orders/{order}/payment-status', [OrderController::class, 'updatePaymentStatus'])->name('orders.updatePaymentStatus');

    // Coupons
    Route::resource('coupons', CouponController::class);

    // Component Types
    Route::resource('component-types', ComponentTypeController::class);

    // Compatibility Rules
    Route::resource('compatibility', CompatibilityController::class);

    // Posts
    Route::resource('posts', PostController::class);
    Route::resource('post-categories', PostCategoryController::class);

    // Pages
    Route::resource('pages', PageController::class);

    // Banners
    Route::resource('banners', BannerController::class);

    // Customers
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

    // Reviews
    Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve');
    Route::patch('reviews/{review}/reject', [ReviewController::class, 'reject'])->name('reviews.reject');
    Route::post('reviews/{review}/reply', [ReviewController::class, 'reply'])->name('reviews.reply');
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // Media Library
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::get('media/browse', [MediaController::class, 'browse'])->name('media.browse');
    Route::post('media/upload', [MediaController::class, 'upload'])->name('media.upload');
    Route::post('media/bulk-delete', [MediaController::class, 'bulkDestroy'])->name('media.bulk-delete');
    Route::post('media/folders', [MediaController::class, 'createFolder'])->name('media.create-folder');
    Route::put('media/{medium}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{medium}', [MediaController::class, 'destroy'])->name('media.destroy');

    // Menus
    Route::resource('menus', MenuController::class)->except(['show']);
    Route::post('menus/{menu}/items', [MenuController::class, 'storeItem'])->name('menus.items.store');
    Route::put('menus/{menu}/items/{item}', [MenuController::class, 'updateItem'])->name('menus.items.update');
    Route::delete('menus/{menu}/items/{item}', [MenuController::class, 'destroyItem'])->name('menus.items.destroy');
    Route::post('menus/{menu}/reorder', [MenuController::class, 'reorderItems'])->name('menus.items.reorder');

    // Settings
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
});
