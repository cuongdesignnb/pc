<?php

namespace App\Exceptions;

use RuntimeException;

class CheckoutQuoteException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'CHECKOUT_QUOTE_INVALID',
        public readonly int $httpStatus = 409,
    ) {
        parent::__construct($message);
    }
}
