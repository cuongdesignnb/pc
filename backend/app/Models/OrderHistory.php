<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line on an order's timeline: a status change ("status") or a KIOT sync
 * event ("kiot"), with who or what did it and why.
 */
class OrderHistory extends Model
{
    public const UPDATED_AT = null;

    public const ACTOR_LABELS = [
        'customer' => 'Khách hàng',
        'admin' => 'Nhân viên',
        'system' => 'Hệ thống web',
        'kiot' => 'KIOT',
        'payment' => 'Cổng thanh toán',
        'api' => 'API',
    ];

    protected $fillable = ['order_id', 'type', 'from_status', 'to_status', 'actor_type', 'actor_id', 'actor_name', 'message'];

    protected $appends = ['actor_label'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getActorLabelAttribute(): string
    {
        $label = self::ACTOR_LABELS[$this->actor_type] ?? $this->actor_type;

        return $this->actor_name ? "{$label}: {$this->actor_name}" : $label;
    }
}
