<?php

declare(strict_types=1);

namespace utils;

use DateTimeImmutable;

final class DateWindow
{
    private const int DAY_START_HOUR = 6;

    public static function logicalWorkdayForStart(DateTimeImmutable $start): string
    {
        if ((int)$start->format('H') < self::DAY_START_HOUR) {
            $start = $start->modify('-1 day');
        }

        return $start->format('Y-m-d');
    }

    public static function queryBounds(string $from, string $to): array
    {
        $fromDate = new DateTimeImmutable($from);
        $toDate = new DateTimeImmutable($to);

        $start = $fromDate->modify('-1 day')->format('Y-m-d H:i:s');
        $end = $toDate->modify('+1 day')->format('Y-m-d H:i:s');

        return [$start, $end];
    }
}
