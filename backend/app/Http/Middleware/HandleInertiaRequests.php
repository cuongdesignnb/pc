<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Models\Setting;
use App\Support\PublicAssetUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar' => $user->avatar,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->allPermissionNames(),
                ] : null,
            ],
            'siteName' => fn (): string => $this->siteName(),
            'siteLogo' => fn (): ?string => $this->siteLogo(),
            'admin' => [
                'pending_orders_count' => fn (): int => $this->pendingOrdersCount($request),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'catalog_result' => fn () => $request->session()->get('catalog_result'),
                'feed_url' => fn () => $request->session()->pull('feed_url'),
            ],
        ];
    }

    private function siteName(): string
    {
        try {
            if (! Schema::hasTable('settings')) {
                return (string) config('app.name', '');
            }

            return trim((string) Setting::get('site_name', config('app.name', '')));
        } catch (\Throwable) {
            // The admin login must still render while a fresh installation is
            // being migrated or when the settings table is temporarily
            // unavailable.
            return (string) config('app.name', '');
        }
    }

    private function siteLogo(): ?string
    {
        try {
            if (! Schema::hasTable('settings')) {
                return null;
            }

            foreach (['site_logo_white', 'site_logo'] as $key) {
                $value = Setting::get($key);
                if (! is_string($value)) {
                    continue;
                }

                $logo = PublicAssetUrl::normalize($value);
                if (is_string($logo) && trim($logo) !== '') {
                    return $logo;
                }
            }
        } catch (\Throwable) {
            // Keep admin usable while settings/storage are unavailable.
        }

        return null;
    }

    private function pendingOrdersCount(Request $request): int
    {
        if (! $request->is('admin', 'admin/*')) {
            return 0;
        }

        try {
            return Schema::hasTable('orders')
                ? Order::query()->where('order_status', 'pending')->count()
                : 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
