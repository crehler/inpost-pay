<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto;

use Symfony\Component\HttpFoundation\Request;

final readonly class TransactionQueryDto
{
    public function __construct(
        public int $page = 0,
        public int $perPage = 20,
        public string $sortBy = 'created_date',
        public string $sortDirection = 'DESC',
        public ?string $orderId = null,
        public ?string $transactionId = null,
        public ?string $merchantPosId = null,
        public ?float $amountFrom = null,
        public ?float $amountTo = null,
        public ?string $currency = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?array $paymentMethod = null,
        public ?array $status = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $paymentMethods = $request->query->all('paymentMethod');
        $statuses = $request->query->all('status');

        return new self(
            page: (int) $request->query->get('page', 0),
            perPage: (int) $request->query->get('per_page', 20),
            sortBy: $request->query->getString('sort_by', 'created_date'),
            sortDirection: $request->query->getString('sort_direction', 'DESC'),
            orderId: $request->query->get('order_id'),
            transactionId: $request->query->get('transaction_id'),
            merchantPosId: $request->query->get('merchant_pos_id'),
            amountFrom: $request->query->has('amount_from')
                ? (float) $request->query->get('amount_from')
                : null,
            amountTo: $request->query->has('amount_to')
                ? (float) $request->query->get('amount_to')
                : null,
            currency: $request->query->get('currency'),
            dateFrom: $request->query->get('date_from'),
            dateTo: $request->query->get('date_to'),
            paymentMethod: !empty($paymentMethods) ? $paymentMethods : null,
            status: !empty($statuses) ? $statuses : null,
        );
    }

    public function toQueryParams(): array
    {
        $params = [
            'page' => $this->page,
            'per_page' => $this->perPage,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];

        if ($this->orderId !== null) {
            $params['order_id'] = $this->orderId;
        }

        if ($this->transactionId !== null) {
            $params['transaction_id'] = $this->transactionId;
        }

        if ($this->merchantPosId !== null) {
            $params['merchant_pos_id'] = $this->merchantPosId;
        }

        if ($this->amountFrom !== null) {
            $params['amount_from'] = $this->amountFrom;
        }

        if ($this->amountTo !== null) {
            $params['amount_to'] = $this->amountTo;
        }

        if ($this->currency !== null) {
            $params['currency'] = $this->currency;
        }

        if ($this->dateFrom !== null) {
            $params['date_from'] = $this->dateFrom;
        }

        if ($this->dateTo !== null) {
            $params['date_to'] = $this->dateTo;
        }

        if ($this->paymentMethod !== null) {
            $params['paymentMethod'] = $this->paymentMethod;
        }

        if ($this->status !== null) {
            $params['status'] = $this->status;
        }

        return $params;
    }
}
