<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Entity;

use Crehler\InpostPay\Domain\ValueObject\{Money, ProductType};
use InvalidArgumentException;

use function array_map;
use function count;
use function filter_var;
use function sprintf;

readonly class BasketProduct
{
    public function __construct(
        public string $productId,
        public string $productName,
        public Money $basePrice,
        public ProductQuantity $quantity,
        public ?ProductType $productType = null,
        public ?string $productCategory = null,
        public ?string $ean = null,
        public ?string $productDescription = null,
        public ?string $productLink = null,
        public ?string $productImage = null,
        public array $additionalProductImages = [],
        public ?Money $promoPrice = null,
        public ?Money $lowestPrice = null,
        public array $productAttributes = [],
        public array $deliveryProduct = [],
    ) {
        $this->validate();
    }

    public function hasPromotion(): bool
    {
        return $this->promoPrice !== null;
    }

    public function getEffectivePrice(): Money
    {
        return $this->promoPrice ?? $this->basePrice;
    }

    public function calculateTotalPrice(): Money
    {
        $effectivePrice = $this->getEffectivePrice();
        $quantity = $this->quantity->quantity;

        if ($this->quantity->isInteger()) {
            return Money::fromFloat(
                $effectivePrice->getNet() * $quantity,
                $effectivePrice->getGross() * $quantity,
                $effectivePrice->getVat() * $quantity,
            );
        }

        return $effectivePrice;
    }

    public function isPhysical(): bool
    {
        if ($this->productType === null) {
            return true;
        }

        return $this->productType->isPhysical();
    }

    public function isDigital(): bool
    {
        return $this->productType === ProductType::DIGITAL;
    }

    public function hasOmnibusPrice(): bool
    {
        return $this->lowestPrice !== null;
    }

    public function toArray(): array
    {
        $data = [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'base_price' => $this->basePrice->toArray(),
            'quantity' => $this->quantity->toArray(),
        ];

        if ($this->productType !== null) {
            $data['product_type'] = $this->productType->value;
        }

        if ($this->productCategory !== null) {
            $data['product_category'] = $this->productCategory;
        }

        if ($this->ean !== null) {
            $data['ean'] = $this->ean;
        }

        if ($this->productDescription !== null) {
            $data['product_description'] = $this->productDescription;
        }

        if ($this->productLink !== null) {
            $data['product_link'] = $this->productLink;
        }

        if ($this->productImage !== null) {
            $data['product_image'] = $this->productImage;
        }

        if (!empty($this->additionalProductImages)) {
            $data['additional_product_images'] = array_map(
                fn (ProductImage $image) => $image->toArray(),
                $this->additionalProductImages
            );
        }

        if ($this->promoPrice !== null) {
            $data['promo_price'] = $this->promoPrice->toArray();
        }

        if ($this->lowestPrice !== null) {
            $data['lowest_price'] = $this->lowestPrice->toArray();
        }

        if (!empty($this->productAttributes)) {
            $data['product_attributes'] = array_map(
                fn (ProductAttribute $attr) => $attr->toArray(),
                $this->productAttributes
            );
        }

        if (!empty($this->deliveryProduct)) {
            $data['delivery_product'] = array_map(
                fn (ProductDelivery $delivery) => $delivery->toArray(),
                $this->deliveryProduct
            );
        }

        return $data;
    }

    private function validate(): void
    {
        if (empty($this->productId)) {
            throw new InvalidArgumentException('Product ID cannot be empty');
        }

        if (empty($this->productName)) {
            throw new InvalidArgumentException('Product name cannot be empty');
        }

        if ($this->productLink !== null && !filter_var($this->productLink, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(sprintf('Invalid product link URL: %s', $this->productLink));
        }

        if ($this->productImage !== null && !filter_var($this->productImage, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(sprintf('Invalid product image URL: %s', $this->productImage));
        }

        // Validate additional images
        foreach ($this->additionalProductImages as $image) {
            if (!$image instanceof ProductImage) {
                throw new InvalidArgumentException('All additional images must be ProductImage instances');
            }
        }
        if (count($this->additionalProductImages) > 10) {
            throw new InvalidArgumentException('Maximum 10 additional images allowed');
        }
        foreach ($this->productAttributes as $attribute) {
            if (!$attribute instanceof ProductAttribute) {
                throw new InvalidArgumentException('All attributes must be ProductAttribute instances');
            }
        }
        foreach ($this->deliveryProduct as $delivery) {
            if (!$delivery instanceof ProductDelivery) {
                throw new InvalidArgumentException('All delivery products must be ProductDelivery instances');
            }
        }
        if ($this->promoPrice !== null && $this->promoPrice->getGross() > $this->basePrice->getGross()) {
            throw new InvalidArgumentException('Promo price cannot be higher than base price');
        }
    }
}
