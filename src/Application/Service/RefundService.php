<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Application\Dto\{RefundRequestDto, RefundResponseDto};
use Crehler\InpostPay\Infrastructure\Client\InpostPayClient;
use Crehler\InpostPay\Infrastructure\Provider\InpostPayConfigProvider;
use Crehler\InpostPay\Infrastructure\Service\RefundSignatureGenerator;

final readonly class RefundService
{
    public function __construct(
        private InpostPayClient $client,
        private InpostPayAuthenticator $authenticator,
        private InpostPayConfigProvider $configProvider,
        private RefundSignatureGenerator $signatureGenerator,
    ) {
    }

    public function requestRefund(RefundRequestDto $dto): RefundResponseDto
    {
        $config = $this->configProvider->getWidgetConfig();
        $auth = $this->authenticator->authenticate();

        $requestBody = $dto->toRequestBody();

        $signature = $this->signatureGenerator->generate(
            commandId: $dto->commandId,
            transactionId: $dto->transactionId,
            requestBody: $requestBody,
            merchantSecret: $config->merchantSecret,
        );

        $requestBody['signature'] = $signature;

        $responseData = $this->client->requestRefund(
            transactionId: $dto->transactionId,
            commandId: $dto->commandId,
            data: $requestBody,
            bearerToken: $auth->token,
            mode: $config->mode,
        );

        return RefundResponseDto::fromApiResponse($responseData);
    }
}
