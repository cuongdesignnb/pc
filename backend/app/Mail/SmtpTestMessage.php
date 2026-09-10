<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SmtpTestMessage extends Mailable
{
    use Queueable;

    public function __construct(public readonly string $siteName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Kiểm tra cấu hình SMTP - '.$this->siteName);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.smtp-test',
            with: ['siteName' => $this->siteName],
        );
    }
}
