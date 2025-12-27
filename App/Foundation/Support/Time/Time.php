<?php

namespace App\Foundation\Support\Time;

use DateTime;
use DateTimeZone;
use JsonSerializable;

class Time implements JsonSerializable
{
    protected string $format;
    protected ?string $formatWhenString = null;
    private DateTime|false $time;
    protected DateTimeZone $timeZone;
    public const DEFAULT_FORMAT = 'Y-m-d H:i:s';
    public const MYSQL_DATETIME_FORMAT = self::DEFAULT_FORMAT;

    public function __construct(
        string|DateTime $time,
        string $format = self::DEFAULT_FORMAT,
        ?DateTimeZone $tz = null,
        ?string $formatWhenString = null
    ) {
        if ($time instanceof DateTime) {
            $result = $time;
        } else {
            $result = DateTime::createFromFormat(
                $format,
                $time,
                $this->timeZone = $tz ?? new DateTimeZone(date_default_timezone_get())
            );
        }

        if ($result === false) {
            throw new \InvalidArgumentException("Invalid time format: {$time}");
        }


        $this->time = $result;
        $this->format = $format;
        $this->formatWhenString = $formatWhenString;
    }

    public static function from(string $value, string $format = 'Y-m-d H:i:s', ?string $timeZone = null): self
    {
        return new self($value, $format, $timeZone != null ? new DateTimeZone($timeZone) : null);
    }

    public function jsonSerialize(): mixed
    {
        return $this->__toString();
    }

    public function __toString(): string
    {
        return $this->time->format($this->formatWhenString ?? $this->format);
    }

    public function format($format){
        return $this->time->format($format);
    }

    public function formatWhenString(string $format)
    {
        $this->formatWhenString = $format;
        return $this;
    }
}
