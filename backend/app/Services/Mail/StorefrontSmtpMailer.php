<?php

namespace App\Services\Mail;

use App\Exceptions\SmtpConfigurationException;
use App\Mail\NewOrderNotification;
use App\Mail\SmtpTestMessage;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StorefrontSmtpMailer
{
    public function sendOrderNotification(Order $order): void
    {
        if (! $this->notificationsEnabled()) {
            return;
        }

        $recipients = $this->orderRecipients();
        if ($recipients === []) {
            Log::warning('Order email notification skipped because no valid recipient is configured.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        $this->configureMailer();
        Mail::mailer('smtp')->to($recipients)->send(new NewOrderNotification($order));
    }

    public function sendTest(string $recipient): void
    {
        $this->configureMailer();
        $siteName = $this->string('site_name', (string) config('app.name'));
        Mail::mailer('smtp')->to($recipient)->send(new SmtpTestMessage($siteName));
    }

    private function notificationsEnabled(): bool
    {
        return $this->boolean('smtp_enabled') && $this->boolean('smtp_order_notifications_enabled');
    }

    private function configureMailer(): void
    {
        $host = $this->string('smtp_host');
        $port = (int) Setting::get('smtp_port', 587);
        $fromAddress = $this->string('smtp_from_address', $this->string('contact_email', (string) config('mail.from.address')));
        $fromName = $this->string('smtp_from_name', $this->string('site_name', (string) config('mail.from.name')));

        if ($host === '') {
            throw new SmtpConfigurationException('Vui lòng nhập máy chủ SMTP trước khi gửi email.');
        }
        if ($port < 1 || $port > 65535) {
            throw new SmtpConfigurationException('Cổng SMTP không hợp lệ.');
        }
        if (! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new SmtpConfigurationException('Vui lòng nhập địa chỉ email người gửi hợp lệ.');
        }

        config([
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => $this->nullable('smtp_username'),
            'mail.mailers.smtp.password' => $this->nullable('smtp_password'),
            'mail.mailers.smtp.scheme' => $this->transportScheme(),
            'mail.mailers.smtp.auto_tls' => $this->smtpEncryption() !== 'none',
            'mail.mailers.smtp.require_tls' => $this->smtpEncryption() === 'tls',
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        $manager = app('mail.manager');
        if (method_exists($manager, 'purge')) {
            $manager->purge('smtp');
        }
    }

    /** @return array<int, string> */
    private function orderRecipients(): array
    {
        $recipients = preg_split('/[\s,;]+/', $this->string('smtp_order_recipient')) ?: [];

        return array_values(array_unique(array_filter($recipients, fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
    }

    private function transportScheme(): ?string
    {
        return $this->smtpEncryption() === 'ssl' ? 'smtps' : 'smtp';
    }

    private function smtpEncryption(): string
    {
        $encryption = strtolower($this->string('smtp_encryption', 'tls'));

        return in_array($encryption, ['tls', 'ssl', 'none'], true) ? $encryption : 'tls';
    }

    private function string(string $key, string $fallback = ''): string
    {
        $value = Setting::get($key, $fallback);

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    private function nullable(string $key): ?string
    {
        $value = $this->string($key);

        return $value === '' ? null : $value;
    }

    private function boolean(string $key): bool
    {
        return filter_var(Setting::get($key, false), FILTER_VALIDATE_BOOLEAN);
    }
}
