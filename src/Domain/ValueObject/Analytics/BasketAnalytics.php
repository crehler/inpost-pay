<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject\Analytics;

readonly class BasketAnalytics
{
    public function __construct(
        public ?string $clientId = null,
        public ?string $gclid = null,
        public ?string $fbclid = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->clientId === null
            && $this->gclid === null
            && $this->fbclid === null;
    }

    public function hasClientId(): bool
    {
        return $this->clientId !== null && $this->clientId !== '';
    }

    /**
     * Returns array of key-value pairs for order_additional_parameters.
     * Format: [['key' => 'client_id', 'value' => '...'], ...]
     *
     * @return array<int, array{key: string, value: string}>
     */
    public function toKeyValueArray(): array
    {
        $params = [];

        if ($this->clientId !== null && $this->clientId !== '') {
            $params[] = ['key' => 'client_id', 'value' => $this->clientId];
        }

        if ($this->gclid !== null && $this->gclid !== '') {
            $params[] = ['key' => 'gclid', 'value' => $this->gclid];
        }

        if ($this->fbclid !== null && $this->fbclid !== '') {
            $params[] = ['key' => 'fbclid', 'value' => $this->fbclid];
        }

        return $params;
    }

    /**
     * Returns associative array for basket_additional_parameters.
     * Format: ['client_id' => '...']
     *
     * @return array<string, string>
     */
    public function toAssocArray(): array
    {
        $params = [];

        if ($this->clientId !== null && $this->clientId !== '') {
            $params['client_id'] = $this->clientId;
        }

        return $params;
    }

    /**
     * Returns full associative array with all parameters.
     *
     * @return array<string, string>
     */
    public function toFullAssocArray(): array
    {
        $params = [];

        if ($this->clientId !== null && $this->clientId !== '') {
            $params['client_id'] = $this->clientId;
        }

        if ($this->gclid !== null && $this->gclid !== '') {
            $params['gclid'] = $this->gclid;
        }

        if ($this->fbclid !== null && $this->fbclid !== '') {
            $params['fbclid'] = $this->fbclid;
        }

        return $params;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            clientId: $data['client_id'] ?? null,
            gclid: $data['gclid'] ?? null,
            fbclid: $data['fbclid'] ?? null,
        );
    }
}
