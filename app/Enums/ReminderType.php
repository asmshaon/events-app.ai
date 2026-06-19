<?php

namespace App\Enums;

enum ReminderType: string
{
    case ThreeDay = '3_day';
    case TwentyFourHour = '24_hour';

    /** Human-readable lead time, used in email subjects/bodies. */
    public function label(): string
    {
        return match ($this) {
            self::ThreeDay => '3 days',
            self::TwentyFourHour => '24 hours',
        };
    }
}
