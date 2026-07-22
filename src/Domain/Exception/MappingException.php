<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

class MappingException extends RuntimeException
{
    public static function failedToMapCart(string $basketId, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Failed to map cart to InpostBasket for basket %s: %s', $basketId, $reason),
            0,
            $previous
        );
    }

    public static function missingRequiredData(string $basketId, string $field): self
    {
        return new self(
            sprintf('Missing required data for basket %s: %s', $basketId, $field)
        );
    }
}
