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
use function strtolower;

class InvalidOrderException extends DomainException
{
    public static function missingShippingMethodMapping(string $deliveryType): self
    {
        return new self(
            sprintf(
                'No Shopware shipping method configured for InPost delivery type "%s". Configure mapping in plugin settings: InpostPay.config.%sShippingMethods',
                $deliveryType,
                strtolower($deliveryType)
            )
        );
    }
}
