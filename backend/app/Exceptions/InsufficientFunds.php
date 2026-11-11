<?php

namespace App\Exceptions;

use App\Support\Money;
use RuntimeException;

class InsufficientFunds extends RuntimeException
{
    public function __construct(public readonly int $shortBy)
    {
        parent::__construct('Not enough in your wallet. Add '.Money::format($shortBy).' more.');
    }
}
