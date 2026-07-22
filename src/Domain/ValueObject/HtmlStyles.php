<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

use JsonSerializable;

use function sprintf;
use function trim;

final readonly class HtmlStyles implements JsonSerializable
{
    public function __construct(
        public ?int $marginTop = null,
        public ?int $marginBottom = null,
        public ?int $marginLeft = null,
        public ?int $marginRight = null,
        public ?string $justifyContent = null,
    ) {
    }

    public function toCssArray(): array
    {
        $styles = [];

        if ($this->marginTop !== null) {
            $styles['margin-top'] = sprintf('%dpx', $this->marginTop);
        }

        if ($this->marginBottom !== null) {
            $styles['margin-bottom'] = sprintf('%dpx', $this->marginBottom);
        }

        if ($this->marginLeft !== null) {
            $styles['margin-left'] = sprintf('%dpx', $this->marginLeft);
        }

        if ($this->marginRight !== null) {
            $styles['margin-right'] = sprintf('%dpx', $this->marginRight);
        }

        if ($this->justifyContent !== null) {
            $styles['display'] = 'flex';
            $styles['flex-wrap'] = 'wrap';
            $styles['justify-content'] = $this->justifyContent;
        }

        return $styles;
    }

    public function toCssString(): string
    {
        $styles = $this->toCssArray();

        if (empty($styles)) {
            return '';
        }

        $cssString = '';
        foreach ($styles as $property => $value) {
            $cssString .= sprintf('%s: %s; ', $property, $value);
        }

        return trim($cssString);
    }

    public function jsonSerialize(): array
    {
        return [
            'marginTop' => $this->marginTop,
            'marginBottom' => $this->marginBottom,
            'marginLeft' => $this->marginLeft,
            'marginRight' => $this->marginRight,
            'justifyContent' => $this->justifyContent,
        ];
    }
}
