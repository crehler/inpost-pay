<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto;

use function array_map;

final readonly class TransactionItemDto
{
    public function __construct(
        public string $transactionId,
        public string $merchantPosId,
        public ?string $externalTransactionId,
        public ?string $description,
        public string $status,
        public string $createdDate,
        public float $amount,
        public string $currency,
        public string $paymentMethod,
        public string $orderId,
        public array $operations,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $operations = array_map(
            fn (array $op) => TransactionOperationDto::fromArray($op),
            $data['operations'] ?? []
        );

        return new self(
            transactionId: $data['transaction_id'] ?? '',
            merchantPosId: $data['merchant_pos_id'] ?? '',
            externalTransactionId: $data['external_transaction_id'] ?? null,
            description: $data['description'] ?? null,
            status: $data['status'] ?? '',
            createdDate: $data['created_date'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            currency: $data['currency'] ?? 'PLN',
            paymentMethod: $data['payment_method'] ?? '',
            orderId: $data['order_id'] ?? '',
            operations: $operations,
        );
    }

    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'merchant_pos_id' => $this->merchantPosId,
            'external_transaction_id' => $this->externalTransactionId,
            'description' => $this->description,
            'status' => $this->status,
            'created_date' => $this->createdDate,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->paymentMethod,
            'order_id' => $this->orderId,
            'operations' => array_map(fn (TransactionOperationDto $op) => $op->toArray(), $this->operations),
        ];
    }
}
