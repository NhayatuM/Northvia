<?php

declare(strict_types=1);

namespace Carbon;

use DateTime;
use DateTimeZone;

/**
 * Simple Carbon-like class for date handling
 * This is a simplified version to avoid external dependencies
 */
class Carbon extends DateTime
{
    public function __construct($time = 'now', DateTimeZone $timezone = null)
    {
        parent::__construct($time, $timezone);
    }

    public static function now(DateTimeZone $timezone = null): self
    {
        return new self('now', $timezone);
    }

    public static function parse($time, DateTimeZone $timezone = null): self
    {
        return new self($time, $timezone);
    }

    public static function createFromFormat($format, $time, DateTimeZone $timezone = null): self
    {
        $date = parent::createFromFormat($format, $time, $timezone);
        if ($date === false) {
            throw new \InvalidArgumentException('Invalid date format');
        }
        
        $carbon = new self();
        $carbon->setTimestamp($date->getTimestamp());
        if ($timezone) {
            $carbon->setTimezone($timezone);
        }
        
        return $carbon;
    }

    public function toDateString(): string
    {
        return $this->format('Y-m-d');
    }

    public function toDateTimeString(): string
    {
        return $this->format('Y-m-d H:i:s');
    }

    public function toTimeString(): string
    {
        return $this->format('H:i:s');
    }

    public function toISOString(): string
    {
        return $this->format('c');
    }

    public function addMinutes(int $minutes): self
    {
        return $this->modify("+{$minutes} minutes");
    }

    public function addHours(int $hours): self
    {
        return $this->modify("+{$hours} hours");
    }

    public function addDays(int $days): self
    {
        return $this->modify("+{$days} days");
    }

    public function addMonths(int $months): self
    {
        return $this->modify("+{$months} months");
    }

    public function addYear(): self
    {
        return $this->modify("+1 year");
    }

    public function subMinutes(int $minutes): self
    {
        return $this->modify("-{$minutes} minutes");
    }

    public function subHours(int $hours): self
    {
        return $this->modify("-{$hours} hours");
    }

    public function subDays(int $days): self
    {
        return $this->modify("-{$days} days");
    }

    public function isFuture(): bool
    {
        return $this > new self();
    }

    public function isPast(): bool
    {
        return $this < new self();
    }

    public function isToday(): bool
    {
        return $this->toDateString() === (new self())->toDateString();
    }

    public function diffInMinutes(Carbon $date = null): int
    {
        $date = $date ?: new self();
        $diff = $this->diff($date);
        return $diff->days * 24 * 60 + $diff->h * 60 + $diff->i;
    }

    public function diffInHours(Carbon $date = null): int
    {
        return (int) ($this->diffInMinutes($date) / 60);
    }

    public function diffInDays(Carbon $date = null): int
    {
        $date = $date ?: new self();
        return $this->diff($date)->days;
    }

    public function getAge(): int
    {
        $now = new self();
        return $now->diff($this)->y;
    }
}