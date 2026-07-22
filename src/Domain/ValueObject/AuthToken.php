<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

use DateInterval;
use DateTime;
use InvalidArgumentException;

final readonly class AuthToken
{
    public function __construct(
        public string $token,
        public DateTime $expiresAt,
    ) {
        if (empty($token)) {
            throw new InvalidArgumentException('Token cannot be empty');
        }

        if ($expiresAt <= new DateTime()) {
            throw new InvalidArgumentException('Token expiration time must be in the future');
        }
    }

    public function isExpired(): bool
    {
        $buffer = new DateInterval('PT5M'); // 5 minutes
        $expiresAt = (clone $this->expiresAt)->sub($buffer);

        return $expiresAt <= new DateTime();
    }

    public function toAuthorizationHeader(): string
    {
        return 'Bearer ' . $this->token;
    }
}
