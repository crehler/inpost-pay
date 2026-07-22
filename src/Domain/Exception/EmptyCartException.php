<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Exception;

use DomainException;

use function sprintf;

class EmptyCartException extends DomainException
{
    public function __construct(string $message = 'Cart is empty, cannot bind with InPost Pay')
    {
        parent::__construct($message);
    }

    public static function forBasket(string $basketId): self
    {
        return new self(
            sprintf('Cannot bind empty cart with basketId "%s" to InPost Pay', $basketId)
        );
    }
}
