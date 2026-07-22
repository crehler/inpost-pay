<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Application\Dto\WebhookPayloadDto;
use Crehler\InpostPay\Application\Handler\Webhook\WebhookHandlerRegistry;
use Crehler\InpostPay\Domain\Exception\InvalidWebhookSignatureException;
use Crehler\InpostPay\Domain\ValueObject\WebhookResult;
use Crehler\InpostPay\Infrastructure\Provider\InpostPayConfigProvider;

final readonly class WebhookService
{
    public function __construct(
        private WebhookSignatureValidator $signatureValidator,
        private WebhookHandlerRegistry $handlerRegistry,
        private InpostPayConfigProvider $configProvider,
    ) {
    }

    public function processWebhook(WebhookPayloadDto $dto): WebhookResult
    {
        $config = $this->configProvider->getWidgetConfig();
        $merchantSecret = $config->merchantSecret;

        if (!$merchantSecret) {
            throw InvalidWebhookSignatureException::missingSecret();
        }

        $isValid = $this->signatureValidator->validate(
            apiVersion: $dto->apiVersion,
            payload: $dto->toArray(),
            receivedSignature: $dto->signature,
            merchantSecret: $merchantSecret,
            eventType: $dto->eventType,
        );

        if (!$isValid) {
            throw InvalidWebhookSignatureException::mismatch();
        }

        return $this->handlerRegistry->getHandler($dto->eventType)->handle($dto);
    }
}
