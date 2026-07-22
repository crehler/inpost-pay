<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Handler\Webhook;

use Crehler\InpostPay\Domain\Exception\UnsupportedWebhookEventException;
use Crehler\InpostPay\Domain\ValueObject\WebhookEventType;

final readonly class WebhookHandlerRegistry
{
    private iterable $handlers;

    public function __construct(iterable $handlers)
    {
        $this->handlers = $handlers;
    }

    public function getHandler(WebhookEventType $eventType): WebhookHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($eventType)) {
                return $handler;
            }
        }

        throw UnsupportedWebhookEventException::forType($eventType->value);
    }
}
