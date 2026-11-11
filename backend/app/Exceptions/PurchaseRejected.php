<?php

namespace App\Exceptions;

use RuntimeException;

/** A purchase stopped before any money moved. Safe to show the message to the user. */
class PurchaseRejected extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'rejected', public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
