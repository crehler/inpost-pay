<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject\Order;

readonly class Consent
{
    public function __construct(
        public string $id,
        public bool $isAccepted,
        public ?string $version = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'consent_id' => $this->id,
            'is_accepted' => $this->isAccepted,
        ];

        if ($this->version !== null) {
            $data['consent_version'] = $this->version;
        }

        return $data;
    }
}
