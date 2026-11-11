<?php

namespace App\Support;

use Illuminate\Support\Str;

final class References
{
    /** Customer-facing reference, e.g. HP-7K2Q9M4X. */
    public static function transaction(): string
    {
        return 'HP-'.strtoupper(Str::random(8));
    }

    /**
     * VTpass requires request_id to start with YYYYMMDDHHii in Africa/Lagos time
     * and be at least 12 characters.
     */
    public static function vtpassRequestId(): string
    {
        return now('Africa/Lagos')->format('YmdHi').strtoupper(Str::random(10));
    }

    public static function checkout(): string
    {
        return 'HPW-'.strtoupper(Str::random(10));
    }
}
