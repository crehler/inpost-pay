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
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('inpost_pay.webhook_handler')]
final readonly class SettlementWebhookHandler implements WebhookHandlerInterface
{
    public function __construct()
    {
    }

    public function supports(WebhookEventType $eventType): bool
    {
        return $eventType->isSettlementEvent();
    }

    public function handle(WebhookPayloadDto $dto): WebhookResult
    {
        $eventData = $dto->eventData;

        return WebhookResult::acknowledged('Settlement webhook received', [
            'settlementId' => $eventData['settlementId'] ?? null,
        ]);
    }
}
