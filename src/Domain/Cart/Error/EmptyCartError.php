<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class EmptyCartError extends Error
{
    private const KEY = 'inpost-pay-empty-cart';

    public function __construct(
        private readonly string $basketId = '',
    ) {
        parent::__construct('Cart is empty, cannot bind with InPost Pay');
    }

    public function getId(): string
    {
        return self::KEY;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getLevel(): int
    {
        return self::LEVEL_WARNING;
    }

    public function blockOrder(): bool
    {
        return false;
    }

    public function getParameters(): array
    {
        return [
            'basketId' => $this->basketId,
        ];
    }

    public function isPersistent(): bool
    {
        return false;
    }
}
