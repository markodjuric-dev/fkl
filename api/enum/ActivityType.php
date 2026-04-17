<?php

declare(strict_types=1);

namespace enum;

enum ActivityType: int
{
    case START = 2;
    case CORRECTION = 3;
    case END = 6;

    public function isStart(): bool
    {
        return $this === self::START;
    }

    public function isCorrection(): bool
    {
        return $this === self::CORRECTION;
    }

    public function isEnd(): bool
    {
        return $this === self::END;
    }
}
