<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Handler\Webhook;

use Crehler\InpostPay\Application\Dto\WebhookPayloadDto;
use Crehler\InpostPay\Domain\ValueObject\{WebhookEventType, WebhookResult};

interface WebhookHandlerInterface
{
    public function supports(WebhookEventType $eventType): bool;

    public function handle(WebhookPayloadDto $dto): WebhookResult;
}
