<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto;

final readonly class RefundResponseDto
{
    public function __construct(
        public string $externalRefundId,
        public float $refundAmount,
        public string $status,
        public string $description,
    ) {
    }

    public static function fromApiResponse(array $data): self
    {
        return new self(
            externalRefundId: $data['external_refund_id'] ?? '',
            refundAmount: (float) ($data['refund_amount'] ?? 0),
            status: $data['status'] ?? 'UNKNOWN',
            description: $data['description'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'external_refund_id' => $this->externalRefundId,
            'refund_amount' => $this->refundAmount,
            'status' => $this->status,
            'description' => $this->description,
        ];
    }
}
