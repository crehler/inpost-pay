<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto;

final readonly class TransactionOperationDto
{
    public function __construct(
        public string $externalOperationId,
        public string $type,
        public string $status,
        public float $amount,
        public string $currency,
        public string $operationDate,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            externalOperationId: $data['external_operation_id'] ?? '',
            type: $data['type'] ?? '',
            status: $data['status'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            currency: $data['currency'] ?? 'PLN',
            operationDate: $data['operation_date'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'external_operation_id' => $this->externalOperationId,
            'type' => $this->type,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'operation_date' => $this->operationDate,
        ];
    }
}
