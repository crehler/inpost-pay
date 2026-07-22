<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\MessageQueue;

use Crehler\InpostPay\Application\Service\OrderUpdateNotifier;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendOrderUpdateHandler
{
    public function __construct(
        private OrderUpdateNotifier $notifier,
    ) {
    }

    public function __invoke(SendOrderUpdateMessage $message): void
    {
        $this->notifier->notify(
            $message->orderId,
            $message->orderStatus,
            Context::createDefaultContext(),
        );
    }
}
