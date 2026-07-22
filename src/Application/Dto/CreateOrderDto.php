<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto;

use Crehler\InpostPay\Application\Dto\Order\{AccountInfoDto, ConsentDto, DeliveryDto, InvoiceDetailsDto, OrderDetailsDto};
use Crehler\InpostPay\Domain\Exception\InvalidBasketException;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

use function array_map;
use function sprintf;

readonly class CreateOrderDto
{
    public function __construct(
        #[Assert\NotNull(message: 'Order details are required')]
        public OrderDetailsDto $orderDetails,

        #[Assert\NotNull(message: 'Account info is required')]
        public AccountInfoDto $accountInfo,

        #[Assert\NotNull(message: 'Delivery details are required')]
        public DeliveryDto $delivery,

        #[Assert\NotNull(message: 'Consents are required')]
        #[Assert\Count(min: 1, minMessage: 'At least one consent is required')]
        public array $consents,

        public ?InvoiceDetailsDto $invoiceDetails = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            $orderDetails = OrderDetailsDto::fromArray($data['order_details'] ?? []);
            $accountInfo = AccountInfoDto::fromArray($data['account_info'] ?? []);
            $delivery = DeliveryDto::fromArray($data['delivery'] ?? []);

            $consents = [];
            if (!empty($data['consents'])) {
                $consents = array_map(
                    fn (array $consent) => ConsentDto::fromArray($consent),
                    $data['consents']
                );
            }

            $invoiceDetails = null;
            if (!empty($data['invoice_details'])) {
                $invoiceDetails = InvoiceDetailsDto::fromArray($data['invoice_details']);
            }

            return new self(
                orderDetails: $orderDetails,
                accountInfo: $accountInfo,
                delivery: $delivery,
                consents: $consents,
                invoiceDetails: $invoiceDetails,
            );
        } catch (InvalidBasketException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidBasketException(sprintf('Invalid order data: %s', $e->getMessage()));
        }
    }

    public function toArray(): array
    {
        $data = [
            'order_details' => $this->orderDetails->toArray(),
            'account_info' => $this->accountInfo->toArray(),
            'delivery' => $this->delivery->toArray(),
            'consents' => array_map(fn (ConsentDto $c) => $c->toArray(), $this->consents),
        ];

        if ($this->invoiceDetails && $this->invoiceDetails->toArray()) {
            $data['invoice_details'] = $this->invoiceDetails->toArray();
        }

        return $data;
    }
}
