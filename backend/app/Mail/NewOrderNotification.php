<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewOrderNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Đơn hàng mới {$this->order->order_number}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.new-order',
            with: ['site_name' => (string) Setting::get('site_name', config('app.name'))],
        );
    }
}
