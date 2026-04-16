<?php

declare(strict_types=1);

namespace utils;

use DateTimeImmutable;

final class DateWindow
{
    public static function logicalWorkdayForStart(DateTimeImmutable $start): string
    {
        if ((int)$start->format('H') < 6) {
            $start = $start->modify('-1 day');
        }

        return $start->format('Y-m-d');
    }

    public static function queryBounds(string $from, string $to): array
    {
        $fromDate = new DateTimeImmutable($from . ' 00:00:00');
        $toDate = new DateTimeImmutable($to . ' 23:59:59');

        // Includes early-hours rows that belong to previous logical day.
        $lower = $fromDate->modify('-1 day')->format('Y-m-d H:i:s');
        $upper = $toDate->modify('+1 day')->format('Y-m-d H:i:s');

        return [$lower, $upper];
    }
}
