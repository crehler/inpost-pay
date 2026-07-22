<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

use InvalidArgumentException;

readonly class BrowserInfo
{
    public function __construct(
        public bool $browserTrusted,
        public ?string $browserId = null,
    ) {
        if (!$this->browserTrusted && $this->browserId !== null) {
            throw new InvalidArgumentException('Non-trusted browser cannot have a browser ID');
        }
    }

    public function isTrusted(): bool
    {
        return $this->browserTrusted;
    }

    public function hasBrowserId(): bool
    {
        return $this->browserId !== null;
    }

    public function toArray(): array
    {
        $data = [
            'browser_trusted' => $this->browserTrusted,
        ];

        if ($this->browserId !== null) {
            $data['browser_id'] = $this->browserId;
        }

        return $data;
    }
}
