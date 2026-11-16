<?php

namespace App\Exceptions;

use RuntimeException;

/** An order change that is refused (wrong transition, overpayment...), with a code for API callers. */
class OrderWriteException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }
}
