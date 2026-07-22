<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject\Order;

use Crehler\InpostPay\Domain\ValueObject\{KeyValue, Money, PaymentType};

use function array_map;

readonly class OrderDetails
{
    public function __construct(
        public string $basketId,
        public string $currency,
        public PaymentType $paymentType,
        public Money $basketPrice,
        public ?string $orderComments = null,
        public ?array $basketAdditionalParameters = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'basket_id' => $this->basketId,
            'currency' => $this->currency,
            'payment_type' => $this->paymentType->value,
            'basket_price' => $this->basketPrice->toArray(),
        ];

        if ($this->orderComments) {
            $data['order_comments'] = $this->orderComments;
        }
        if ($this->basketAdditionalParameters) {
            $data['basket_additional_parameters'] = array_map(
                fn (KeyValue $kv) => $kv->toArray(),
                $this->basketAdditionalParameters
            );
        }

        return $data;
    }
}
