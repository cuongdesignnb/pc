<?php

use App\Http\Controllers\Admin\AiArticleController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CatalogChannelController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CompatibilityController;
use App\Http\Controllers\Admin\ComponentTypeController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FilterController;
use App\Http\Controllers\Admin\KiotIntegrationController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PostCategoryController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CatalogFeedController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('feeds/google/products.xml', [CatalogFeedController::class, 'google'])->name('feeds.google.products');
Route::get('feeds/meta/products.csv', [CatalogFeedController::class, 'meta'])->name('feeds.meta.products');

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

// ─── Admin Auth Routes (guest only) ─────────────────────────────────────────
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');
});

// ─── Admin Protected Routes ─────────────────────────────────────────────────
Route::prefix('admin')->middleware(['web', 'admin.auth'])->name('admin.')->group(function () {
    // Logout
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Products
    Route::resource('products', ProductController::class);
    Route::get('products-export', [ProductController::class, 'export'])->name('products.export');
    Route::post('products-import', [ProductController::class, 'import'])->name('products.import');

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

    // Filters
    Route::resource('filters', FilterController::class);

    // Category filter assignment
    Route::get('categories/{category}/filters', [CategoryController::class, 'editFilters'])->name('categories.filters.edit');
    Route::put('categories/{category}/filters', [CategoryController::class, 'updateFilters'])->name('categories.filters.update');

    // Compatibility Rules
    Route::resource('compatibility', CompatibilityController::class);

    // Posts
    Route::resource('posts', PostController::class);
    Route::get('posts-export', [PostController::class, 'export'])->name('posts.export');
    Route::post('posts-import', [PostController::class, 'import'])->name('posts.import');
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

    Route::middleware('permission:settings.view')->group(function () {
        Route::get('integrations/kiot', [KiotIntegrationController::class, 'index'])->name('integrations.kiot.index');
    });
    Route::middleware('permission:settings.edit')->group(function () {
        Route::post('integrations/kiot/pair', [KiotIntegrationController::class, 'pair'])->name('integrations.kiot.pair');
        Route::post('integrations/kiot/manual', [KiotIntegrationController::class, 'manual'])->name('integrations.kiot.manual');
        Route::post('integrations/kiot/import-environment', [KiotIntegrationController::class, 'importEnvironment'])->name('integrations.kiot.import-environment');
        Route::post('integrations/kiot/test-connection', [KiotIntegrationController::class, 'testConnection'])->name('integrations.kiot.test-connection');
        Route::patch('integrations/kiot/flags', [KiotIntegrationController::class, 'updateFlags'])->name('integrations.kiot.flags');
        Route::post('integrations/kiot/disconnect', [KiotIntegrationController::class, 'disconnect'])->name('integrations.kiot.disconnect');
        Route::post('integrations/kiot/dry-run', [KiotIntegrationController::class, 'dryRun'])->name('integrations.kiot.dry-run');
        Route::post('integrations/kiot/test-one', [KiotIntegrationController::class, 'testSku'])->name('integrations.kiot.test-one');
        Route::post('integrations/kiot/sync-one', [KiotIntegrationController::class, 'syncOne'])->name('integrations.kiot.sync-one');
        Route::post('integrations/kiot/sync', [KiotIntegrationController::class, 'sync'])->name('integrations.kiot.sync');
        Route::post('integrations/kiot/incremental', [KiotIntegrationController::class, 'incremental'])->name('integrations.kiot.incremental');
        Route::get('integrations/kiot/runs/{run}', [KiotIntegrationController::class, 'showRun'])->name('integrations.kiot.runs.show');
        Route::post('integrations/kiot/retry', [KiotIntegrationController::class, 'retry'])->name('integrations.kiot.retry');
        Route::post('integrations/kiot/events/{event}/retry', [KiotIntegrationController::class, 'retryEvent'])->name('integrations.kiot.events.retry');
    });

    Route::middleware('permission:catalog-channels.view')->group(function () {
        Route::get('integrations/catalog-channels', [CatalogChannelController::class, 'index'])
            ->name('integrations.catalog-channels.index');
    });
    Route::middleware('permission:catalog-channels.manage')->group(function () {
        Route::patch('integrations/catalog-channels/google-sheets/config', [CatalogChannelController::class, 'updateGoogleSheets'])
            ->name('integrations.catalog-channels.google-sheets.config');
        Route::post('integrations/catalog-channels/google-sheets/test', [CatalogChannelController::class, 'testGoogleSheets'])
            ->name('integrations.catalog-channels.google-sheets.test');
        Route::post('integrations/catalog-channels/google-sheets/dry-run', [CatalogChannelController::class, 'dryRunGoogleSheets'])
            ->name('integrations.catalog-channels.google-sheets.dry-run');
        Route::post('integrations/catalog-channels/google-sheets/sync', [CatalogChannelController::class, 'syncGoogleSheets'])
            ->name('integrations.catalog-channels.google-sheets.sync');
        Route::patch('integrations/catalog-channels/{channel}/price', [CatalogChannelController::class, 'updatePriceSelection'])
            ->whereIn('channel', ['website', 'google_sheets', 'google_merchant', 'meta_catalog'])
            ->name('integrations.catalog-channels.price');
        Route::post('integrations/catalog-channels/price-books/sync', [CatalogChannelController::class, 'syncPriceBooks'])
            ->name('integrations.catalog-channels.price-books.sync');
        Route::post('integrations/catalog-channels/product-prices/sync', [CatalogChannelController::class, 'syncProductPrices'])
            ->name('integrations.catalog-channels.product-prices.sync');
        Route::patch('integrations/catalog-channels/{channel}/flags', [CatalogChannelController::class, 'updateFlags'])
            ->whereIn('channel', ['google_merchant', 'meta_catalog'])
            ->name('integrations.catalog-channels.flags');
        Route::post('integrations/catalog-channels/{channel}/validate', [CatalogChannelController::class, 'validateFeed'])
            ->whereIn('channel', ['google_merchant', 'meta_catalog'])
            ->name('integrations.catalog-channels.validate');
        Route::post('integrations/catalog-channels/meta_catalog/test-connection', [CatalogChannelController::class, 'testMetaConnection'])
            ->name('integrations.catalog-channels.meta_catalog.test-connection');
        Route::post('integrations/catalog-channels/{channel}/rebuild', [CatalogChannelController::class, 'rebuildFeed'])
            ->whereIn('channel', ['google_merchant', 'meta_catalog'])
            ->name('integrations.catalog-channels.rebuild');
        Route::post('integrations/catalog-channels/{channel}/rotate-token', [CatalogChannelController::class, 'rotateToken'])
            ->whereIn('channel', ['google_merchant', 'meta_catalog'])
            ->name('integrations.catalog-channels.rotate-token');
    });

    // AI Articles
    Route::get('ai-articles', [AiArticleController::class, 'index'])->name('ai-articles.index');
    Route::get('ai-articles/create', [AiArticleController::class, 'create'])->name('ai-articles.create');
    Route::post('ai-articles', [AiArticleController::class, 'store'])->name('ai-articles.store');
    Route::get('ai-articles/{aiArticle}', [AiArticleController::class, 'show'])->name('ai-articles.show');
    Route::post('ai-articles/{aiArticle}/run', [AiArticleController::class, 'run'])->name('ai-articles.run');
    Route::delete('ai-articles/{aiArticle}', [AiArticleController::class, 'destroy'])->name('ai-articles.destroy');
    Route::post('ai-articles/generate-single', [AiArticleController::class, 'generateSingle'])->name('ai-articles.generate-single');

    // ─── User & Role Management (restricted to users with permission) ────
    Route::middleware('permission:users.view')->group(function () {
        Route::resource('users', UserController::class);
    });

    Route::middleware('permission:roles.view')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });
});
