<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto;

use Crehler\InpostPay\Application\Dto\EventData\PaymentEventData;
use Crehler\InpostPay\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

use function sprintf;

final readonly class OrderEventDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Event ID is required')]
        #[Assert\Type(type: 'string', message: 'Event ID must be a string')]
        public string $eventId,

        #[Assert\NotNull(message: 'Event date/time is required')]
        public DateTimeImmutable $eventDateTime,

        #[Assert\NotNull(message: 'Phone number is required')]
        public PhoneNumber $phoneNumber,

        #[Assert\NotNull(message: 'Event data is required')]
        public PaymentEventData $eventData,
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            $eventDateTime = new DateTimeImmutable($data['event_data_time'] ?? '');

            $phoneNumber = new PhoneNumber(
                $data['phone_number']['country_prefix'] ?? '',
                $data['phone_number']['phone'] ?? ''
            );

            $eventData = PaymentEventData::fromArray($data['event_data'] ?? []);

            return new self(
                eventId: (string) ($data['event_id'] ?? ''),
                eventDateTime: $eventDateTime,
                phoneNumber: $phoneNumber,
                eventData: $eventData,
            );
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(sprintf('Invalid order event data: %s', $e->getMessage()), 0, $e);
        } catch (Exception $e) {
            throw new InvalidArgumentException(sprintf('Failed to parse order event: %s', $e->getMessage()), 0, $e);
        }
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_data_time' => $this->eventDateTime->format(DateTimeImmutable::ATOM),
            'phone_number' => $this->phoneNumber->toArray(),
            'event_data' => $this->eventData->toArray(),
        ];
    }
}
