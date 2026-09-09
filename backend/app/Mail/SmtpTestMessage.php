<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SmtpTestMessage extends Mailable
{
    use Queueable;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Kiểm tra cấu hình SMTP');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.smtp-test');
    }
}
