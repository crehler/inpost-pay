<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

enum BasketConfirmationStatus: string
{
    case SUCCESS = 'SUCCESS';
    case REJECT = 'REJECT';

    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECT;
    }
}
