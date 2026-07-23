<?php

namespace App\Services\Integrations\Kiot;

use Illuminate\Http\Client\Response;

class KiotResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $body,
    ) {}

    public static function fromHttp(Response $response): self
    {
        return new self($response->status(), $response->json() ?? []);
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300 && ($this->body['success'] ?? false);
    }

    public function duplicate(): bool
    {
        return $this->successful() && ($this->body['duplicate'] ?? false);
    }

    public function data(): array
    {
        return $this->body['data'] ?? [];
    }

    public function meta(): array
    {
        return $this->body['meta'] ?? [];
    }

    public function errorCode(): ?string
    {
        return $this->body['error']['code'] ?? null;
    }

    public function errorMessage(): string
    {
        return $this->body['error']['message'] ?? 'KIOT trả về phản hồi không hợp lệ.';
    }
}
