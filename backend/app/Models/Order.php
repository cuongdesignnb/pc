<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $hidden = [
        'checkout_idempotency_key',
        'order_access_token_hash',
        'kiot_event_id',
        'kiot_idempotency_key',
        'kiot_payload_hash',
        'kiot_last_attempt_at',
        'kiot_sync_error_message',
        'kiot_response',
    ];

    protected $fillable = [
        'user_id',
        'order_number',
        'subtotal',
        'discount',
        'shipping_fee',
        'total',
        'payment_status',
        'payment_method',
        'order_status',
        'shipping_name',
        'shipping_phone',
        'customer_email',
        'shipping_address',
        'shipping_city',
        'shipping_district',
        'shipping_ward',
        'notes',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'checkout_idempotency_key',
        'order_access_token_hash',
        'kiot_event_id',
        'kiot_idempotency_key',
        'kiot_order_id',
        'kiot_order_code',
        'kiot_sync_status',
        'kiot_sync_attempt_count',
        'kiot_payload_hash',
        'kiot_last_attempt_at',
        'kiot_synced_at',
        'kiot_sync_error_code',
        'kiot_sync_error_message',
        'kiot_response',
    ];

    protected $casts = [
        'subtotal' => 'decimal:0',
        'discount' => 'decimal:0',
        'shipping_fee' => 'decimal:0',
        'total' => 'decimal:0',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'kiot_order_id' => 'integer',
        'kiot_sync_attempt_count' => 'integer',
        'kiot_last_attempt_at' => 'datetime',
        'kiot_synced_at' => 'datetime',
        'kiot_response' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    public static function generateOrderNumber(): string
    {
        return 'DH'.date('Ymd').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function canPay(): bool
    {
        return $this->kiot_sync_status === 'synced'
            && $this->payment_method === 'sepay'
            && $this->payment_status === 'unpaid'
            && $this->order_status !== 'cancelled';
    }

    public function canCancel(): bool
    {
        return in_array($this->order_status, ['pending', 'confirmed'], true)
            && ! in_array($this->kiot_sync_status, ['rejected', 'cancelled', 'cancel_pending'], true);
    }

    public function matchesAccessToken(?string $token): bool
    {
        return is_string($token)
            && $token !== ''
            && is_string($this->order_access_token_hash)
            && hash_equals($this->order_access_token_hash, self::hashAccessToken($token));
    }

    public static function hashAccessToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
