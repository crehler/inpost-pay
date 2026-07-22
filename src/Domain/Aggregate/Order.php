<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Aggregate;

use Crehler\InpostPay\Domain\Entity\OrderLine;
use Crehler\InpostPay\Domain\ValueObject\{KeyValue, PaymentType};
use Crehler\InpostPay\Domain\ValueObject\Order\{Consent, CustomerInfo, DeliveryDetails, InvoiceDetails, OrderPricing};
use DateTimeInterface;
use DateTimeZone;

use function array_map;

class Order
{
    private ?string $inPostOrderId = null;

    public function __construct(
        private string $id,
        private string $merchantBasketId,
        private string $merchantPosId,
        private string $status,
        private string $currency,
        private DateTimeInterface $createdAt,
        private CustomerInfo $customer,
        private DeliveryDetails $delivery,
        private OrderPricing $pricing,
        private PaymentType $paymentType,
        private array $items,
        private array $consents = [],
        private array $additionalParams = [],
        private ?InvoiceDetails $invoice = null,
        private ?string $comments = null,
        private array $deliveryReferences = [],
        private ?string $merchantStatusDesc = null,
        private ?string $customerOrderId = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function confirmOrder(string $externalId, OrderPricing $finalPricing): void
    {
        $this->inPostOrderId = $externalId;
        $this->pricing = $finalPricing;
        $this->status = 'CONFIRMED';
    }

    public function toArray(): array
    {
        $utcCreatedAt = $this->createdAt->setTimezone(new DateTimeZone('UTC'));
        $orderCreationDate = $utcCreatedAt->format('Y-m-d\TH:i:s.000\Z');

        $data = [
            'order_details' => [
                'order_id' => $this->id,
                'customer_order_id' => $this->customerOrderId,
                'pos_id' => $this->merchantPosId,
                'order_creation_date' => $orderCreationDate,
                'order_merchant_status_description' => $this->merchantStatusDesc ?? '',
                'basket_id' => $this->merchantBasketId,
                'currency' => $this->currency,
                'payment_type' => $this->paymentType->value,
                'order_base_price' => $this->pricing->base->toArray(),
                'order_final_price' => $this->pricing->final->toArray(),
                'order_discount' => $this->pricing->discount ?? 0.0,
                'delivery_references_list' => $this->deliveryReferences,
            ],
            'account_info' => $this->customer->toArray(),
            'delivery' => $this->delivery->toArray(),
            'products' => array_map(fn (OrderLine $line) => $line->toArray(), $this->items),
            'consents' => array_map(fn (Consent $consent) => $consent->toArray(), $this->consents),
        ];

        if ($this->invoice) {
            $data['invoice_details'] = $this->invoice->toArray();
        }

        if ($this->comments) {
            $data['order_details']['order_comments'] = $this->comments;
        }

        if (!empty($this->additionalParams)) {
            $data['order_details']['basket_additional_parameters'] = array_map(
                fn (KeyValue $kv) => $kv->toArray(),
                $this->additionalParams
            );
        }

        return $data;
    }
}
