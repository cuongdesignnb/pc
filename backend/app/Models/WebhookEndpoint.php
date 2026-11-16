<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single outbound webhook (hook.laptopplus.vn). The secret is stored
 * encrypted and only shown once, when it is generated.
 */
class WebhookEndpoint extends Model
{
    public const EVENTS = [
        'order.created' => 'Đơn mới trên website',
        'order.status_changed' => 'Đổi trạng thái đơn',
        'order.payment_changed' => 'Thay đổi thanh toán',
        'contact.created' => 'Liên hệ mới từ website',
    ];

    protected $fillable = ['enabled', 'url', 'secret', 'events', 'secret_rotated_at'];

    protected $hidden = ['secret'];

    protected $casts = [
        'enabled' => 'boolean',
        'secret' => 'encrypted',
        'events' => 'array',
        'secret_rotated_at' => 'datetime',
    ];

    /** The saved configuration, or an unsaved "off" one (reading never writes). */
    public static function current(): self
    {
        return static::query()->orderBy('id')->first() ?? new static(['enabled' => false, 'events' => []]);
    }

    public function subscribes(string $event): bool
    {
        return $this->enabled && filled($this->url) && filled($this->secret)
            && in_array($event, (array) $this->events, true);
    }
}
