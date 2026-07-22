<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto\EventData;

use Crehler\InpostPay\Domain\Exception\InvalidBasketException;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use ValueError;

use function sprintf;

readonly class RelatedProductEventData
{
    public function __construct(
        #[Assert\NotBlank(message: 'Product ID is required')]
        #[Assert\Type(type: 'string', message: 'Product ID must be a string')]
        public string $productId,

        #[Assert\NotNull(message: 'Quantity is required')]
        #[Assert\Type(type: 'int', message: 'Quantity must be an integer')]
        #[Assert\GreaterThan(value: 0, message: 'Quantity must be greater than 0')]
        public int $quantity = 1,

        #[Assert\Type(type: 'string', message: 'Product name must be a string')]
        public ?string $productName = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            return new self(
                productId: $data['product_id'] ?? '',
                quantity: (int) ($data['quantity'] ?? 1),
                productName: $data['product_name'] ?? null,
            );
        } catch (ValueError|InvalidArgumentException $e) {
            throw new InvalidBasketException(sprintf('Invalid related product event data: %s', $e->getMessage()));
        }
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'product_name' => $this->productName,
        ];
    }
}
