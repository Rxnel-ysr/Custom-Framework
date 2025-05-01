<?php
class Unix
{
    public static function days(int|float $days): int|float
    {
        return $days * 86400;
    }

    public static function hours(int|float $hours): int|float
    {
        return $hours * 3600;
    }

    public static function minutes(int|float $minutes): int|float
    {
        return $minutes * 60;
    }
}