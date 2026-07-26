<?php

namespace App\Support;

use Illuminate\Support\Str;

final class CanonicalEmail
{
    public static function from(string $email): string
    {
        return Str::lower(trim($email));
    }
}
