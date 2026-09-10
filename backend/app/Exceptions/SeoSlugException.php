<?php

namespace App\Exceptions;

use InvalidArgumentException;

class SeoSlugException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'INVALID_SLUG',
    ) {
        parent::__construct($message);
    }
}
