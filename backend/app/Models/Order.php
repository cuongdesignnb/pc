<?php

namespace App\Models;

use App\Services\Affiliate\CommissionService;
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
        'affiliate_id',
        'referral_code',
        'referral_discount',
        'order_number',
        'subtotal',
        'discount',
        'shipping_fee',
        'total',
        'payment_status',
        'payment_method',
        'payment_option',
        'deposit_amount',
        'amount_paid',
        'deposit_paid_at',
        'checkout_mode',
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
        'referral_discount' => 'decimal:0',
        'shipping_fee' => 'decimal:0',
        'total' => 'decimal:0',
        'deposit_amount' => 'decimal:0',
        'amount_paid' => 'decimal:0',
        'paid_at' => 'datetime',
        'deposit_paid_at' => 'datetime',
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

    protected static function booted(): void
    {
        static::updating(function (Order $order): void {
            if ($order->isDirty('order_status') && $order->order_status === 'shipping' && ! $order->shipped_at) {
                $order->shipped_at = now();
            }
            if ($order->isDirty('order_status') && $order->order_status === 'delivered' && ! $order->delivered_at) {
                $order->delivered_at = now();
            }
        });
        static::updated(function (Order $order): void {
            if ($order->affiliate_id && $order->wasChanged('order_status')) {
                app(CommissionService::class)->syncWithOrder($order);
            }
        });
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function affiliateCommission(): HasOne
    {
        return $this->hasOne(AffiliateCommission::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->orderBy('id');
    }

    public static function generateOrderNumber(): string
    {
        return 'DH'.date('Ymd').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * A transfer can be taken once KIOT has accepted the order, or right away
     * when the order never goes to KIOT. A deposited order stays payable so
     * the customer can transfer the rest instead of paying on delivery.
     */
    public function canPay(): bool
    {
        return $this->acceptsPayments()
            && $this->payment_method === 'sepay'
            && in_array($this->payment_status, ['unpaid', 'deposited'], true)
            && $this->order_status !== 'cancelled';
    }

    public function acceptsPayments(): bool
    {
        return in_array($this->kiot_sync_status, ['synced', 'not_required'], true);
    }

    public function remainingAmount(): int
    {
        return max((int) $this->total - (int) $this->amount_paid, 0);
    }

    /** What the next transfer should be: the deposit first if one was chosen, otherwise the rest. */
    public function amountDue(): int
    {
        if ($this->payment_status === 'unpaid' && $this->payment_option === 'deposit' && (int) $this->deposit_amount > 0) {
            return max((int) $this->deposit_amount - (int) $this->amount_paid, 0) ?: $this->remainingAmount();
        }

        return $this->remainingAmount();
    }

    public function canCancel(): bool
    {
        return in_array($this->order_status, ['pending', 'confirmed'], true)
            && ! in_array($this->kiot_sync_status, ['rejected', 'cancelled', 'cancel_pending'], true);
    }

    public function canResendToKiot(): bool
    {
        return in_array($this->kiot_sync_status, ['not_required', 'failed'], true)
            && $this->order_status !== 'cancelled';
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
