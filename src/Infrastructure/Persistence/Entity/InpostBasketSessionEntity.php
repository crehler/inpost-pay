<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Persistence\Entity;

use Crehler\InpostPay\Domain\ValueObject\Analytics\BasketAnalytics;
use DateTimeImmutable;
use Shopware\Core\Framework\DataAbstractionLayer\{Entity, EntityIdTrait};

class InpostBasketSessionEntity extends Entity
{
    use EntityIdTrait;

    protected string $basketId;

    protected ?string $cartToken = null;

    protected string $salesChannelId;

    protected ?string $inpostBasketId = null;

    protected ?string $basketBindingApiKey = null;

    protected ?array $confirmationResponse = null;

    protected ?string $analyticsClientId = null;

    protected ?string $analyticsGclid = null;

    protected ?string $analyticsFbclid = null;

    protected ?string $orderId = null;

    protected DateTimeImmutable $boundAt;

    public function getBasketId(): string
    {
        return $this->basketId;
    }

    public function setBasketId(string $basketId): void
    {
        $this->basketId = $basketId;
    }

    public function getCartToken(): ?string
    {
        return $this->cartToken;
    }

    public function setCartToken(?string $cartToken): void
    {
        $this->cartToken = $cartToken;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getInpostBasketId(): ?string
    {
        return $this->inpostBasketId;
    }

    public function setInpostBasketId(?string $inpostBasketId): void
    {
        $this->inpostBasketId = $inpostBasketId;
    }

    public function getBasketBindingApiKey(): ?string
    {
        return $this->basketBindingApiKey;
    }

    public function setBasketBindingApiKey(?string $basketBindingApiKey): void
    {
        $this->basketBindingApiKey = $basketBindingApiKey;
    }

    public function getConfirmationResponse(): ?array
    {
        return $this->confirmationResponse;
    }

    public function setConfirmationResponse(?array $confirmationResponse): void
    {
        $this->confirmationResponse = $confirmationResponse;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getBoundAt(): DateTimeImmutable
    {
        return $this->boundAt;
    }

    public function setBoundAt(DateTimeImmutable $boundAt): void
    {
        $this->boundAt = $boundAt;
    }

    public function getAnalyticsClientId(): ?string
    {
        return $this->analyticsClientId;
    }

    public function setAnalyticsClientId(?string $analyticsClientId): void
    {
        $this->analyticsClientId = $analyticsClientId;
    }

    public function getAnalyticsGclid(): ?string
    {
        return $this->analyticsGclid;
    }

    public function setAnalyticsGclid(?string $analyticsGclid): void
    {
        $this->analyticsGclid = $analyticsGclid;
    }

    public function getAnalyticsFbclid(): ?string
    {
        return $this->analyticsFbclid;
    }

    public function setAnalyticsFbclid(?string $analyticsFbclid): void
    {
        $this->analyticsFbclid = $analyticsFbclid;
    }

    public function getBasketAnalytics(): BasketAnalytics
    {
        return new BasketAnalytics(
            clientId: $this->analyticsClientId,
            gclid: $this->analyticsGclid,
            fbclid: $this->analyticsFbclid,
        );
    }
}
