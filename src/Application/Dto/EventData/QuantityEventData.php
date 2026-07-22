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

use function is_array;
use function sprintf;

readonly class QuantityEventData
{
    public function __construct(
        #[Assert\NotBlank(message: 'Product ID is required')]
        #[Assert\Type(type: 'string', message: 'Product ID must be a string')]
        public string $productId,

        #[Assert\NotNull(message: 'Quantity is required')]
        #[Assert\Type(type: 'int', message: 'Quantity must be an integer')]
        public int $quantity,

        #[Assert\Type(type: 'string', message: 'Quantity type must be a string')]
        public ?string $quantityType = null,

        #[Assert\Type(type: 'string', message: 'Quantity unit must be a string')]
        public ?string $quantityUnit = null,

        #[Assert\Type(type: 'int', message: 'Available quantity must be an integer')]
        public ?int $availableQuantity = null,

        #[Assert\Type(type: 'int', message: 'Max quantity must be an integer')]
        public ?int $maxQuantity = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            $quantityData = $data['quantity'] ?? 0;
            $quantity = is_array($quantityData) ? ($quantityData['quantity'] ?? 0) : $quantityData;

            return new self(
                productId: $data['product_id'] ?? '',
                quantity: (int) $quantity,
                quantityType: $data['quantity_type'] ?? null,
                quantityUnit: $data['quantity_unit'] ?? null,
                availableQuantity: isset($data['available_quantity']) ? (int) $data['available_quantity'] : null,
                maxQuantity: isset($data['max_quantity']) ? (int) $data['max_quantity'] : null,
            );
        } catch (ValueError|InvalidArgumentException $e) {
            throw new InvalidBasketException(sprintf('Invalid quantity event data: %s', $e->getMessage()));
        }
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'quantity_type' => $this->quantityType,
            'quantity_unit' => $this->quantityUnit,
            'available_quantity' => $this->availableQuantity,
            'max_quantity' => $this->maxQuantity,
        ];
    }
}
