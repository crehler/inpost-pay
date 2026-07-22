<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

use Crehler\InpostPay\Domain\Enum\BindingPlace;
use JsonSerializable;

use function implode;

final readonly class WidgetDisplayConfig implements JsonSerializable
{
    /**
     * @var string
     */
    public const VARIANT_PRIMARY = 'primary';
    /**
     * @var string
     */
    public const VARIANT_SECONDARY = 'secondary';

    /**
     * @var string
     */
    public const FRAME_STYLE_ROUNDED = 'rounded';
    /**
     * @var string
     */
    public const FRAME_STYLE_ROUND = 'round';

    /**
     * @var string
     */
    public const SIZE_XS = 'size-xs';
    /**
     * @var string
     */
    public const SIZE_SM = 'size-sm';
    /**
     * @var string
     */
    public const SIZE_MD = 'size-md';
    /**
     * @var string
     */
    public const SIZE_LG = 'size-lg';
    /**
     * @var string
     */
    public const SIZE_XL = 'size-xl';

    /**
     * @var string
     */
    public const JUSTIFY_START = 'start';
    /**
     * @var string
     */
    public const JUSTIFY_CENTER = 'center';
    /**
     * @var string
     */
    public const JUSTIFY_END = 'end';

    /**
     * @var int
     */
    public const MIN_WIDTH_PX = 220;
    /**
     * @var int
     */
    public const MAX_WIDTH_PX = 1200;

    public function __construct(
        public BindingPlace $bindingPlace,
        public bool $displayed = true,
        public bool $darkMode = false,
        public string $variant = self::VARIANT_SECONDARY,
        public ?string $frameStyle = null,
        public ?string $size = null,
        public ?int $maxWidthPx = null,
        public ?HtmlStyles $htmlStyles = null,
    ) {
    }

    public function getVariation(): ?string
    {
        $parts = [];

        if ($this->frameStyle !== null) {
            $parts[] = $this->frameStyle;
        }

        if ($this->darkMode) {
            $parts[] = 'dark';
        }

        if ($this->variant === self::VARIANT_PRIMARY) {
            $parts[] = $this->variant;
        }

        if ($this->size !== null) {
            $parts[] = $this->size;
        }

        if (empty($parts)) {
            return null;
        }

        return implode(' ', $parts);
    }

    public function getHtmlStyles(): HtmlStyles
    {
        return $this->htmlStyles ?? new HtmlStyles();
    }

    public function jsonSerialize(): array
    {
        return [
            'bindingPlace' => $this->bindingPlace->value,
            'displayed' => $this->displayed,
            'darkMode' => $this->darkMode,
            'variant' => $this->variant,
            'frameStyle' => $this->frameStyle,
            'size' => $this->size,
            'maxWidthPx' => $this->maxWidthPx,
            'variation' => $this->getVariation(),
            'htmlStyles' => $this->getHtmlStyles(),
        ];
    }

    public static function getAvailableVariants(): array
    {
        return [
            self::VARIANT_PRIMARY,
            self::VARIANT_SECONDARY,
        ];
    }

    public static function getAvailableFrameStyles(): array
    {
        return [
            self::FRAME_STYLE_ROUNDED,
            self::FRAME_STYLE_ROUND,
        ];
    }

    public static function getAvailableSizes(): array
    {
        return [
            self::SIZE_XS,
            self::SIZE_SM,
            self::SIZE_MD,
            self::SIZE_LG,
            self::SIZE_XL,
        ];
    }

    public static function getAvailableJustifyOptions(): array
    {
        return [
            self::JUSTIFY_START,
            self::JUSTIFY_CENTER,
            self::JUSTIFY_END,
        ];
    }
}
