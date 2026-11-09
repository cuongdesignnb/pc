<?php

namespace App\Services\AdminApi;

use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * API tokens for tools outside the admin site. "content" may read and write
 * articles, categories, media and product SEO fields (never price or stock);
 * "readonly" (for KIOT and reports) may only read. "orders" may only list
 * orders, for shop notifications (n8n), and is kept apart from content tokens.
 */
class AdminApiTokens
{
    public const NAME_PREFIX = 'admin-api:';

    public const READ = 'content:read';

    public const WRITE = 'content:write';

    public const ORDERS_READ = 'orders:read';

    public const TYPES = [
        'content' => ['label' => 'Nội dung (đọc + ghi bài viết, danh mục, ảnh, SEO sản phẩm)', 'abilities' => [self::READ, self::WRITE]],
        'readonly' => ['label' => 'Chỉ đọc (ví dụ cho KIOT)', 'abilities' => [self::READ]],
        'orders' => ['label' => 'Đơn hàng – chỉ đọc (tên, SĐT, sản phẩm, tổng tiền của đơn)', 'abilities' => [self::ORDERS_READ]],
    ];

    /** Order tokens expose customer names and phones: only staff who may see orders. */
    public function mayIssue(User $owner, string $type): bool
    {
        return $type !== 'orders' || self::mayReadOrders($owner);
    }

    public static function mayReadOrders(User $user): bool
    {
        return $user->hasRole('super-admin') || $user->checkPermissionTo('orders.view');
    }

    public function issue(User $owner, string $name, string $type, ?int $expiresInDays = null): NewAccessToken
    {
        return $owner->createToken(
            self::NAME_PREFIX.$type.':'.trim($name),
            self::TYPES[$type]['abilities'],
            $expiresInDays ? now()->addDays($expiresInDays) : null,
        );
    }

    /** @return Collection<int, array<string, mixed>> */
    public function all(): Collection
    {
        return PersonalAccessToken::query()
            ->where('name', 'like', self::NAME_PREFIX.'%')
            ->with('tokenable:id,name,email')
            ->latest()
            ->get()
            ->map(function (PersonalAccessToken $token): array {
                [, $type, $name] = array_pad(explode(':', $token->name, 3), 3, '');

                return [
                    'id' => $token->id,
                    'name' => $name,
                    'type' => $type,
                    'type_label' => self::TYPES[$type]['label'] ?? $type,
                    'created_by' => $token->tokenable?->name,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'expires_at' => $token->expires_at?->toIso8601String(),
                    'created_at' => $token->created_at?->toIso8601String(),
                ];
            });
    }

    public function revoke(int $id): bool
    {
        return PersonalAccessToken::query()
            ->whereKey($id)
            ->where('name', 'like', self::NAME_PREFIX.'%')
            ->delete() > 0;
    }
}
