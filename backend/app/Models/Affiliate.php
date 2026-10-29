<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cộng tác viên (CTV). Gets a code such as CTV023 when an admin approves
 * the application; only active CTVs can bring in customers.
 */
class Affiliate extends Model
{
    public const STATUSES = ['pending', 'active', 'locked'];

    protected $fillable = [
        'user_id',
        'code',
        'phone',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'id_card_number',
        'status',
        'payout_days',
        'approved_at',
        'note',
    ];

    protected $casts = [
        'payout_days' => 'array',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(AffiliateCustomer::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** "Nguyễn Văn A – CTV023", as shown on orders and sent to KIOT. */
    public function label(): string
    {
        return trim(($this->user?->name ?? '').' – '.($this->code ?? ''), ' –');
    }

    public static function findActiveByCode(?string $code): ?self
    {
        $code = strtoupper(trim((string) $code));

        return $code === '' ? null : self::where('code', $code)->where('status', 'active')->first();
    }

    /** Next free code: CTV001, CTV002, … */
    public static function nextCode(): string
    {
        $last = self::where('code', 'like', 'CTV%')->pluck('code')
            ->map(fn (string $code): int => (int) substr($code, 3))
            ->max() ?? 0;

        return 'CTV'.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }
}
