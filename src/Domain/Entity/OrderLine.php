<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Entity;

use Crehler\InpostPay\Domain\ValueObject\{Money, ProductAttribute, ProductType, Quantity};

use function array_map;

class OrderLine
{
    public function __construct(
        private string $productId,
        private string $name,
        private ProductType $type,
        private Quantity $quantity,
        private Money $basePrice,
        private ?string $ean = null,
        private ?string $category = null,
        private ?string $description = null,
        private ?string $imageUrl = null,
        private ?string $productUrl = null,
        private ?array $additionalImages = null,
        private array $attributes = [],
        private ?Money $promoPrice = null,
        private ?Money $lowestPrice = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'product_id' => $this->productId,
            'product_name' => $this->name,
            'product_type' => $this->type->value,
            'quantity' => $this->quantity->toArray(),
            'base_price' => $this->basePrice->toArray(),
        ];

        if ($this->ean) {
            $data['ean'] = $this->ean;
        }
        if ($this->category) {
            $data['product_category'] = $this->category;
        }
        if ($this->description) {
            $data['product_description'] = $this->description;
        }
        if ($this->imageUrl) {
            $data['product_image'] = $this->imageUrl;
        }
        if ($this->productUrl) {
            $data['product_link'] = $this->productUrl;
        }
        if ($this->promoPrice !== null) {
            $data['promo_price'] = $this->promoPrice->toArray();
        }
        if ($this->lowestPrice !== null) {
            $data['lowest_price'] = $this->lowestPrice->toArray();
        }
        if ($this->additionalImages) {
            $data['additional_product_images'] = $this->additionalImages;
        }
        if (!empty($this->attributes)) {
            $data['product_attributes'] = array_map(
                fn (ProductAttribute $attr) => $attr->toArray(),
                $this->attributes
            );
        }

        return $data;
    }
}
