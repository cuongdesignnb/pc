<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderHistory;
use Illuminate\Support\Facades\Auth;

/**
 * Who is changing orders right now, so every status change is written to
 * order_histories with an actor and a reason.
 *
 * Code that changes an order wraps the change in actingAs(); a change made
 * without a context is attributed to the signed-in staff member, or to the
 * website system when nobody is signed in.
 */
class OrderHistoryRecorder
{
    /** @var list<array{actor_type: string, actor_id: ?int, actor_name: ?string, message: ?string}> */
    private static array $stack = [];

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function actingAs(string $actorType, ?string $message, callable $callback, ?int $actorId = null, ?string $actorName = null): mixed
    {
        self::$stack[] = ['actor_type' => $actorType, 'actor_id' => $actorId, 'actor_name' => $actorName, 'message' => $message];
        try {
            return $callback();
        } finally {
            array_pop(self::$stack);
        }
    }

    public static function created(Order $order): void
    {
        $context = self::$stack === [] ? ['actor_type' => 'customer', 'actor_id' => null, 'actor_name' => null, 'message' => 'Khách đặt hàng trên website'] : self::context();
        OrderHistory::create($context + [
            'order_id' => $order->id,
            'type' => 'status',
            'from_status' => null,
            'to_status' => (string) $order->order_status,
        ]);
    }

    public static function statusChanged(Order $order, ?string $from, string $to): void
    {
        $context = self::context();
        OrderHistory::create($context + [
            'order_id' => $order->id,
            'type' => 'status',
            'from_status' => $from,
            'to_status' => $to,
        ]);
    }

    public static function kiot(Order|int $order, string $message, string $actorType = 'system'): void
    {
        OrderHistory::create([
            'order_id' => $order instanceof Order ? $order->id : $order,
            'type' => 'kiot',
            'actor_type' => $actorType,
            'message' => mb_substr($message, 0, 1000),
        ]);
    }

    /** @return array{actor_type: string, actor_id: ?int, actor_name: ?string, message: ?string} */
    private static function context(): array
    {
        $current = end(self::$stack) ?: null;
        // Storefront customers share the web guard (Sanctum); only staff count as admin.
        $user = Auth::guard('web')->user();
        if ($user && $user->role === 'customer') {
            $user = null;
        }
        if ($current !== null) {
            if ($current['actor_type'] === 'admin' && $current['actor_id'] === null && $user) {
                $current['actor_id'] = $user->id;
                $current['actor_name'] = $user->name;
            }

            return $current;
        }

        return $user
            ? ['actor_type' => 'admin', 'actor_id' => $user->id, 'actor_name' => $user->name, 'message' => null]
            : ['actor_type' => 'system', 'actor_id' => null, 'actor_name' => null, 'message' => null];
    }
}
