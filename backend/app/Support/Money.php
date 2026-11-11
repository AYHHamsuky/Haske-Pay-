<?php

namespace App\Support;

final class Money
{
    public static function format(int $kobo): string
    {
        $naira = $kobo / 100;
        $decimals = $kobo % 100 === 0 ? 0 : 2;

        return '₦'.number_format($naira, $decimals);
    }

    /** "5,000" or 5000 naira to kobo. */
    public static function fromNaira(int|float|string $naira): int
    {
        return (int) round(((float) str_replace(',', '', (string) $naira)) * 100);
    }
}
