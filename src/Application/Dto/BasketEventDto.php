<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto;

use Crehler\InpostPay\Application\Dto\EventData\{PromoCodeEventData, QuantityEventData, RelatedProductEventData};
use Crehler\InpostPay\Domain\Exception\{InvalidBasketException, UnsupportedEventTypeException};
use Crehler\InpostPay\Domain\ValueObject\{EventType, PhoneNumber};
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use ValueError;

use function array_map;
use function is_array;
use function sprintf;

readonly class BasketEventDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Event ID is required')]
        #[Assert\Type(type: 'string', message: 'Event ID must be a string')]
        public string $eventId,

        #[Assert\NotNull(message: 'Event date/time is required')]
        public DateTimeImmutable $eventDateTime,

        #[Assert\NotNull(message: 'Event type is required')]
        public EventType $eventType,

        #[Assert\NotNull(message: 'Phone number is required')]
        public PhoneNumber $phoneNumber,

        public ?array $eventData = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            $eventType = EventType::from($data['event_type'] ?? '');

            $phoneNumber = new PhoneNumber(
                $data['phone_number']['country_prefix'] ?? '',
                $data['phone_number']['phone'] ?? ''
            );

            $eventDateTime = new DateTimeImmutable($data['event_data_time']);

            $eventData = match ($eventType) {
                EventType::PRODUCTS_QUANTITY => self::parseQuantityEventData($data),
                EventType::PROMO_CODES => self::parsePromoCodeEventData($data),
                EventType::RELATED_PRODUCTS => self::parseRelatedProductEventData($data),
            };

            return new self(
                eventId: $data['event_id'] ?? '',
                eventDateTime: $eventDateTime,
                eventType: $eventType,
                phoneNumber: $phoneNumber,
                eventData: $eventData,
            );
        } catch (ValueError $e) {
            throw UnsupportedEventTypeException::unknown($data['event_type'] ?? 'unknown');
        } catch (InvalidArgumentException $e) {
            throw new InvalidBasketException(sprintf('Invalid basket event data: %s', $e->getMessage()));
        }
    }

    public function toArray(): array
    {
        $eventDataArray = null;
        if ($this->eventData) {
            $eventDataArray = array_map(
                static fn ($item) => $item->toArray(),
                $this->eventData
            );
        }

        return [
            'event_id' => $this->eventId,
            'event_data_time' => $this->eventDateTime->format(DateTimeImmutable::ATOM),
            'event_type' => $this->eventType->value,
            'phone_number' => $this->phoneNumber->toArray(),
            'event_data' => $eventDataArray,
        ];
    }

    private static function parseQuantityEventData(array $data): array
    {
        $eventData = $data['quantity_event_data'] ?? null;

        if (empty($eventData)) {
            throw new InvalidBasketException('Quantity event data is required for PRODUCTS_QUANTITY event type');
        }

        if (!is_array($eventData) || !isset($eventData[0])) {
            $eventData = [$eventData];
        }

        $result = [];
        foreach ($eventData as $item) {
            $result[] = QuantityEventData::fromArray($item);
        }

        return $result;
    }

    private static function parsePromoCodeEventData(array $data): array
    {
        $eventData = $data['promo_codes_event_data'] ?? null;

        // Empty array means "remove all promo codes" - this is valid
        if ($eventData === [] || $eventData === null) {
            return [];
        }

        if (!is_array($eventData) || !isset($eventData[0])) {
            $eventData = [$eventData];
        }

        $result = [];
        foreach ($eventData as $item) {
            $result[] = PromoCodeEventData::fromArray($item);
        }

        return $result;
    }

    private static function parseRelatedProductEventData(array $data): array
    {
        $eventData = $data['related_products_event_data'] ?? $data['related_product_event_data'] ?? null;

        if (empty($eventData)) {
            throw new InvalidBasketException('Related product event data is required for RELATED_PRODUCTS event type');
        }

        if (!is_array($eventData) || !isset($eventData[0])) {
            $eventData = [$eventData];
        }

        $result = [];
        foreach ($eventData as $item) {
            $result[] = RelatedProductEventData::fromArray($item);
        }

        return $result;
    }
}
