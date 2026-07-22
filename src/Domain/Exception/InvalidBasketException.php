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

class InvalidBasketException extends DomainException
{
    public static function expired(string $basketId): self
    {
        return new self(
            sprintf('Basket with ID "%s" has expired', $basketId)
        );
    }

    public static function withInvalidStatus(string $basketId, string $status): self
    {
        return new self(
            sprintf(
                'Cannot process basket with ID "%s" in status "%s". Valid statuses are SUCCESS or REJECT',
                $basketId,
                $status
            )
        );
    }

    public static function missingDeliveryOptions(string $basketId): self
    {
        return new self(
            sprintf('Basket with ID "%s" has no delivery options', $basketId)
        );
    }

    public static function missingPaymentMethods(string $basketId): self
    {
        return new self(
            sprintf('Basket with ID "%s" has no payment methods available', $basketId)
        );
    }
}
