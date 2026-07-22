<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Aggregate;

use Crehler\InpostPay\Domain\Entity\{BasketSummary, DeliveryOption};
use Crehler\InpostPay\Domain\Event\{BasketConfirmedEvent, BasketRejectedEvent};
use Crehler\InpostPay\Domain\ValueObject\{BasketConfirmationStatus, BrowserInfo, Money, PhoneNumber};
use DateTimeImmutable;
use DomainException;

use function reset;
use function sprintf;
use function trim;

final class InpostBasket
{
    private ?object $domainEvent;

    public function __construct(
        private readonly string $basketId,
        private readonly string $inpostBasketId,
        private readonly BasketConfirmationStatus $status,
        private readonly PhoneNumber $phoneNumber,
        private readonly BrowserInfo $browserInfo,
        private readonly BasketSummary $summary,
        private readonly array $deliveryOptions,
        private readonly array $products = [],
        private readonly array $relatedProducts = [],
        private readonly ?string $maskedPhoneNumber = null,
        private readonly ?string $name = null,
        private readonly ?string $surname = null,
        private readonly DateTimeImmutable $confirmedAt = new DateTimeImmutable(),
        private readonly array $consents = [],
        private readonly array $promoCodes = [],
    ) {
        $this->domainEvent = null;
    }

    public function getBasketId(): string
    {
        return $this->basketId;
    }

    public function getInpostBasketId(): string
    {
        return $this->inpostBasketId;
    }

    public function getStatus(): BasketConfirmationStatus
    {
        return $this->status;
    }

    public function getPhoneNumber(): PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function getBrowserInfo(): BrowserInfo
    {
        return $this->browserInfo;
    }

    public function getSummary(): BasketSummary
    {
        return $this->summary;
    }

    public function getDeliveryOptions(): array
    {
        return $this->deliveryOptions;
    }

    public function getProducts(): array
    {
        return $this->products;
    }

    public function getMaskedPhoneNumber(): ?string
    {
        return $this->maskedPhoneNumber;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function getConfirmedAt(): DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function confirm(?DateTimeImmutable $confirmedAt = null): self
    {
        if (!$this->status->isSuccess()) {
            throw new DomainException(sprintf('Cannot confirm basket with status %s. Only SUCCESS status can be confirmed.', $this->status->value));
        }

        if ($this->summary->isExpired()) {
            throw new DomainException('Cannot confirm expired basket');
        }

        $confirmed = clone $this;
        $confirmed->domainEvent = new BasketConfirmedEvent(
            basketId: $this->basketId,
            inpostBasketId: $this->inpostBasketId,
            phoneNumber: $this->phoneNumber,
            basketSummary: $this->summary,
            deliveryOptions: $this->deliveryOptions,
            products: $this->products,
            confirmedAt: $confirmedAt ?? new DateTimeImmutable()
        );

        return $confirmed;
    }

    public function reject(?DateTimeImmutable $rejectedAt = null): self
    {
        if (!$this->status->isRejected()) {
            throw new DomainException(sprintf('Cannot reject basket with status %s. Only REJECT status can be rejected.', $this->status->value));
        }

        $rejected = clone $this;
        $rejected->domainEvent = new BasketRejectedEvent(
            basketId: $this->basketId,
            inpostBasketId: $this->inpostBasketId,
            phoneNumber: $this->phoneNumber,
            rejectedAt: $rejectedAt ?? new DateTimeImmutable()
        );

        return $rejected;
    }

    public function getDomainEvent(): ?object
    {
        return $this->domainEvent;
    }

    public function isProcessed(): bool
    {
        return $this->domainEvent !== null;
    }

    public function isConfirmed(): bool
    {
        return $this->domainEvent instanceof BasketConfirmedEvent;
    }

    public function isRejected(): bool
    {
        return $this->domainEvent instanceof BasketRejectedEvent;
    }

    public function getCustomerFullName(): ?string
    {
        if ($this->name === null || $this->surname === null) {
            return null;
        }

        return trim(sprintf('%s %s', $this->name, $this->surname));
    }

    public function getPrimaryDeliveryOption(): DeliveryOption
    {
        return reset($this->deliveryOptions);
    }

    public function getTotalDeliveryPrice(): Money
    {
        $totalPrice = Money::zero();

        foreach ($this->deliveryOptions as $option) {
            $totalPrice = $totalPrice->add($option->deliveryPrice);
        }

        return $totalPrice;
    }

    public function getTotalPrice(): Money
    {
        $basketPrice = $this->summary->getEffectivePrice();
        $deliveryPrice = $this->getTotalDeliveryPrice();

        return $basketPrice->add($deliveryPrice);
    }

    public function getProductsCount(): int
    {
        $count = 0;
        foreach ($this->products as $product) {
            $count += (int) $product->quantity->quantity;
        }

        return $count;
    }

    public function hasPhysicalProducts(): bool
    {
        foreach ($this->products as $product) {
            if ($product->isPhysical()) {
                return true;
            }
        }

        return false;
    }

    public function hasDigitalProducts(): bool
    {
        foreach ($this->products as $product) {
            if ($product->isDigital()) {
                return true;
            }
        }

        return false;
    }

    public function getProductsTotalPrice(): Money
    {
        $totalPrice = Money::zero();
        foreach ($this->products as $product) {
            $totalPrice = $totalPrice->add($product->calculateTotalPrice());
        }

        return $totalPrice;
    }

    public function getConsents(): array
    {
        return $this->consents;
    }

    public function getRelatedProducts(): array
    {
        return $this->relatedProducts;
    }

    public function getPromoCodes(): array
    {
        return $this->promoCodes;
    }
}
