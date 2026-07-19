<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class KiotIntegrationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly string $classification = 'configuration_failure',
        public readonly ?int $httpStatus = null,
        public readonly ?array $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function retryable(): bool
    {
        return $this->classification === 'retryable';
    }
}
