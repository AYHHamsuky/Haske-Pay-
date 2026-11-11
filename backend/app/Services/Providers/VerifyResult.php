<?php

namespace App\Services\Providers;

final class VerifyResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $name = null,
        public readonly ?string $address = null,
        public readonly ?string $error = null,
        public readonly ?string $band = null,
        public readonly ?int $minAmount = null,
    ) {}

    public static function found(string $name, ?string $address = null, ?string $band = null, ?int $minAmount = null): self
    {
        return new self(true, $name, $address, null, $band, $minAmount);
    }

    public static function notFound(string $error = 'No customer found for this meter. Check the number and the company.'): self
    {
        return new self(false, error: $error);
    }

    public function toArray(): array
    {
        return ['ok' => $this->ok, 'name' => $this->name, 'address' => $this->address, 'band' => $this->band, 'error' => $this->error, 'min_amount' => $this->minAmount];
    }
}
