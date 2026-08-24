<?php

namespace App\Exceptions;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $providerStatus = null)
    {
        parent::__construct($message);
    }
}
