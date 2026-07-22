<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto;

use Crehler\InpostPay\Application\Dto\EventData\OrderUpdateEventData;
use Crehler\InpostPay\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

use function sprintf;

final readonly class OrderUpdateNotificationDto
{
    public function __construct(
        public string $eventId,
        public DateTimeImmutable $eventDateTime,
        public ?PhoneNumber $phoneNumber,
        public OrderUpdateEventData $eventData,
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            $phoneNumber = isset($data['phone_number'])
                ? new PhoneNumber(
                    $data['phone_number']['country_prefix'] ?? '',
                    $data['phone_number']['phone'] ?? ''
                )
                : null;

            return new self(
                eventId: (string) ($data['event_id'] ?? ''),
                eventDateTime: new DateTimeImmutable($data['event_data_time'] ?? ''),
                phoneNumber: $phoneNumber,
                eventData: OrderUpdateEventData::fromArray($data['event_data'] ?? []),
            );
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(sprintf('Invalid order update notification data: %s', $e->getMessage()), 0, $e);
        } catch (Exception $e) {
            throw new InvalidArgumentException(sprintf('Failed to parse order update notification: %s', $e->getMessage()), 0, $e);
        }
    }

    public function toArray(): array
    {
        $utc = $this->eventDateTime->setTimezone(new DateTimeZone('UTC'));

        $data = [
            'event_id' => $this->eventId,
            'event_data_time' => $utc->format('Y-m-d\TH:i:s.000\Z'),
            'event_data' => $this->eventData->toArray(),
        ];

        if ($this->phoneNumber !== null) {
            $data['phone_number'] = $this->phoneNumber->toArray();
        }

        return $data;
    }
}
