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

class InvalidPhoneNumberException extends DomainException
{
    public static function mismatchWithSession(string $basketId): self
    {
        return new self(
            sprintf(
                'Phone number from event does not match session phone for basket "%s"',
                $basketId
            )
        );
    }

    public static function invalid(string $reason): self
    {
        return new self(
            sprintf('Invalid phone number: %s', $reason)
        );
    }
}
