<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;

class LoePeriod
{
    public static function deadline(int $month, int $year, User $user): CarbonImmutable
    {
        return CarbonImmutable::create($year, $month, 1, 23, 59, 59, $user->timezone)->endOfMonth();
    }

    public static function isClosed(int $month, int $year, User $user): bool
    {
        return now($user->timezone)->greaterThan(self::deadline($month, $year, $user));
    }
}
