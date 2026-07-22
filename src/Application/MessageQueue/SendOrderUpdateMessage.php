<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\MessageQueue;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

final readonly class SendOrderUpdateMessage implements AsyncMessageInterface
{
    public function __construct(
        public string $orderId,
        public ?string $orderStatus = null,
    ) {
    }
}
