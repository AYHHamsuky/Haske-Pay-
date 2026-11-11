<?php

namespace App\Services\Providers;

final class ProviderResult
{
    public const SUCCESS = 'success';

    public const FAILED = 'failed';           // definitive: the provider did not and will not deliver

    public const PENDING = 'pending';         // accepted but not final, or we lost the response

    public const UNREACHABLE = 'unreachable'; // the request never reached the provider: safe to fail over

    public const NOT_FOUND = 'not_found';     // requery only: the provider has no record of it

    public function __construct(
        public readonly string $status,
        public readonly ?string $message = null,
        public readonly ?string $providerRef = null,
        public readonly ?string $token = null,
        public readonly ?string $units = null,
        public readonly array $raw = [],
    ) {}

    public static function success(?string $ref = null, ?string $token = null, ?string $units = null, array $raw = []): self
    {
        return new self(self::SUCCESS, null, $ref, $token, $units, $raw);
    }

    public static function failed(string $message, array $raw = []): self
    {
        return new self(self::FAILED, $message, raw: $raw);
    }

    public static function pending(?string $message = null, array $raw = [], ?string $ref = null): self
    {
        return new self(self::PENDING, $message, $ref, raw: $raw);
    }

    public static function unreachable(string $message): self
    {
        return new self(self::UNREACHABLE, $message);
    }

    public static function notFound(array $raw = []): self
    {
        return new self(self::NOT_FOUND, 'Provider has no record of this request.', raw: $raw);
    }

    public function is(string $status): bool
    {
        return $this->status === $status;
    }
}
