<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

use DateTimeInterface;
use JsonSerializable;

readonly class TimeOfWeekRange implements JsonSerializable
{
    public function __construct(
        public TimeOfWeek $start,
        public TimeOfWeek $end,
    ) {
    }

    public static function create(
        int $startDay,
        string $startTime,
        int $endDay,
        string $endTime,
    ): self {
        return new self(
            TimeOfWeek::create($startDay, $startTime),
            TimeOfWeek::create($endDay, $endTime)
        );
    }

    public function contains(DateTimeInterface $dateTime): bool
    {
        $check = TimeOfWeek::fromDateTime($dateTime);
        $checkMinutes = $check->toMinutesFromWeekStart();
        $startMinutes = $this->start->toMinutesFromWeekStart();
        $endMinutes = $this->end->toMinutesFromWeekStart();

        if ($startMinutes <= $endMinutes) {
            return $checkMinutes >= $startMinutes && $checkMinutes < $endMinutes;
        }

        return $checkMinutes >= $startMinutes || $checkMinutes < $endMinutes;
    }

    public function jsonSerialize(): array
    {
        return [
            'start' => $this->start->jsonSerialize(),
            'end' => $this->end->jsonSerialize(),
        ];
    }
}
