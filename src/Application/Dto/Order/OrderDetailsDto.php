<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto\Order;

use Crehler\InpostPay\Domain\Exception\InvalidBasketException;
use Crehler\InpostPay\Domain\ValueObject\{KeyValue, Money, PaymentType};
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;
use ValueError;

use function array_map;
use function sprintf;

readonly class OrderDetailsDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Basket ID is required')]
        #[Assert\Type(type: 'string', message: 'Basket ID must be a string')]
        public string $basketId,

        #[Assert\NotBlank(message: 'Currency is required')]
        #[Assert\Type(type: 'string', message: 'Currency must be a string')]
        #[Assert\Choice(choices: ['PLN'], message: 'Only PLN currency is supported')]
        public string $currency,

        #[Assert\NotNull(message: 'Payment type is required')]
        public PaymentType $paymentType,

        #[Assert\NotNull(message: 'Basket price is required')]
        public Money $basketPrice,

        #[Assert\Type(type: 'string', message: 'Order comments must be a string')]
        public ?string $orderComments = null,

        public ?array $additionalParameters = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            $paymentType = PaymentType::from($data['payment_type'] ?? '');

            $basketPrice = new Money(
                net: (string) ($data['basket_price']['net'] ?? '0.00'),
                gross: (string) ($data['basket_price']['gross'] ?? '0.00'),
                vat: (string) ($data['basket_price']['vat'] ?? '0.00'),
            );

            $additionalParams = null;
            if (!empty($data['basket_additional_parameters'])) {
                $additionalParams = array_map(
                    fn (array $item) => new KeyValue(
                        key: (string) ($item['key'] ?? ''),
                        value: (string) ($item['value'] ?? '')
                    ),
                    $data['basket_additional_parameters']
                );
            }

            return new self(
                basketId: (string) ($data['basket_id'] ?? ''),
                currency: (string) ($data['currency'] ?? ''),
                paymentType: $paymentType,
                basketPrice: $basketPrice,
                orderComments: isset($data['order_comments']) ? (string) $data['order_comments'] : null,
                additionalParameters: $additionalParams,
            );
        } catch (ValueError $e) {
            throw new InvalidBasketException(sprintf('Invalid payment type: %s', $e->getMessage()));
        } catch (Throwable $e) {
            throw new InvalidBasketException(sprintf('Invalid order details: %s', $e->getMessage()));
        }
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

        if ($this->additionalParameters) {
            $data['basket_additional_parameters'] = array_map(
                fn (KeyValue $kv) => $kv->toArray(),
                $this->additionalParameters
            );
        }

        return $data;
    }
}
