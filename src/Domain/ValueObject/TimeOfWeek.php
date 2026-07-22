<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

use function preg_match;
use function sprintf;
use function trim;

readonly class TimeOfWeek implements JsonSerializable
{
    private const TIME_FORMAT = 'H:i:s';

    public function __construct(
        public WeekDay $weekDay,
        public DateTimeImmutable $time,
    ) {
    }

    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        return new self(
            WeekDay::fromDateTime($dateTime),
            DateTimeImmutable::createFromFormat(
                self::TIME_FORMAT,
                $dateTime->format(self::TIME_FORMAT)
            ) ?: new DateTimeImmutable($dateTime->format(self::TIME_FORMAT))
        );
    }

    public static function create(int $weekDay, string $time): self
    {
        return new self(
            WeekDay::from($weekDay),
            self::parseTime($time)
        );
    }

    public function toMinutesFromWeekStart(): int
    {
        $dayMinutes = ($this->weekDay->value - 1) * 24 * 60;
        $timeMinutes = (int) $this->time->format('H') * 60 + (int) $this->time->format('i');

        return $dayMinutes + $timeMinutes;
    }

    public function compare(self $other): int
    {
        return $this->toMinutesFromWeekStart() <=> $other->toMinutesFromWeekStart();
    }

    public function jsonSerialize(): array
    {
        return [
            'weekDay' => $this->weekDay->value,
            'time' => $this->time->format(self::TIME_FORMAT),
        ];
    }

    /**
     * Parse time string from various formats:
     * - "HH:MM" or "HH:MM:SS" (standard format)
     * - "H:MM" (single digit hour)
     * - "8" or "14" (just hour number)
     * - Invalid strings fall back to "00:00:00"
     */
    private static function parseTime(string $time): DateTimeImmutable
    {
        $time = trim($time);

        // Already valid time format (HH:MM or HH:MM:SS)
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) {
            return new DateTimeImmutable($time);
        }

        // Just a number (interpret as hour)
        if (preg_match('/^\d{1,2}$/', $time)) {
            $hour = (int) $time;
            if ($hour >= 0 && $hour <= 23) {
                return new DateTimeImmutable(sprintf('%02d:00:00', $hour));
            }
        }

        // Fallback to midnight for invalid formats
        return new DateTimeImmutable('00:00:00');
    }
}
