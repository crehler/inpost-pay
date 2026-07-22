<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto;

use Crehler\InpostPay\Infrastructure\Serializer\RequestDeserializer;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

final readonly class RefundRequestDto
{
    public function __construct(
        public string $transactionId,
        public string $commandId,
        public float $refundAmount,
        public ?string $externalRefundId = null,
        public ?array $additionalBusinessData = null,
    ) {
    }

    public static function fromRequest(string $transactionId, Request $request, RequestDeserializer $deserializer): self
    {
        $commandId = $request->headers->get('X-Command-ID');

        if (!$commandId) {
            throw new InvalidArgumentException('X-Command-ID header is required');
        }

        $data = $deserializer->decodeRequest($request);

        if (!isset($data['refund_amount'])) {
            throw new InvalidArgumentException('refund_amount is required');
        }

        return new self(
            transactionId: $transactionId,
            commandId: $commandId,
            refundAmount: (float) $data['refund_amount'],
            externalRefundId: $data['external_refund_id'] ?? null,
            additionalBusinessData: $data['additional_business_data'] ?? null,
        );
    }

    public function toRequestBody(): array
    {
        $body = [
            'refund_amount' => $this->refundAmount,
        ];

        if ($this->externalRefundId !== null) {
            $body['external_refund_id'] = $this->externalRefundId;
        }

        if ($this->additionalBusinessData !== null) {
            $body['additional_business_data'] = $this->additionalBusinessData;
        }

        return $body;
    }
}
