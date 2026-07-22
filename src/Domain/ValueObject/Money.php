<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

use InvalidArgumentException;

use function bcadd;
use function bccomp;
use function bcsub;
use function max;
use function number_format;
use function sprintf;

readonly class Money
{
    private const SCALE = 2;

    public function __construct(
        private string $net,
        private string $gross,
        private string $vat,
    ) {
        $this->validate();
    }

    public static function fromFloat(float $net, float $gross, float $vat): self
    {
        return new self(
            number_format($net, self::SCALE, '.', ''),
            number_format($gross, self::SCALE, '.', ''),
            number_format($vat, self::SCALE, '.', '')
        );
    }

    public static function fromShopwarePrice(float $net, float $gross): self
    {
        $net = max(0.0, $net);
        $gross = max(0.0, $gross);

        if ($net > $gross) {
            $net = $gross;
        }

        $netStr = number_format($net, self::SCALE, '.', '');
        $grossStr = number_format($gross, self::SCALE, '.', '');
        $vatStr = bcsub($grossStr, $netStr, self::SCALE);

        return new self($netStr, $grossStr, $vatStr);
    }

    public static function zero(): self
    {
        return new self('0.00', '0.00', '0.00');
    }

    public function getNet(): float
    {
        return (float) $this->net;
    }

    public function getGross(): float
    {
        return (float) $this->gross;
    }

    public function getVat(): float
    {
        return (float) $this->vat;
    }

    public function toArray(): array
    {
        return [
            'net' => (float) $this->net,
            'gross' => (float) $this->gross,
            'vat' => (float) $this->vat,
        ];
    }

    public function isZero(): bool
    {
        return bccomp($this->gross, '0', self::SCALE) === 0;
    }

    public function equals(Money $other): bool
    {
        return bccomp($this->gross, $other->gross, self::SCALE) === 0
            && bccomp($this->net, $other->net, self::SCALE) === 0;
    }

    public function add(Money $other): self
    {
        return new self(
            bcadd($this->net, $other->net, self::SCALE),
            bcadd($this->gross, $other->gross, self::SCALE),
            bcadd($this->vat, $other->vat, self::SCALE)
        );
    }

    private function validate(): void
    {
        if (bccomp($this->net, '0', self::SCALE) < 0) {
            throw new InvalidArgumentException('Net amount cannot be negative');
        }

        if (bccomp($this->gross, '0', self::SCALE) < 0) {
            throw new InvalidArgumentException('Gross amount cannot be negative');
        }

        if (bccomp($this->vat, '0', self::SCALE) < 0) {
            throw new InvalidArgumentException('VAT amount cannot be negative');
        }

        $calculatedGross = bcadd($this->net, $this->vat, self::SCALE);

        if (bccomp($calculatedGross, $this->gross, self::SCALE) !== 0) {
            throw new InvalidArgumentException(sprintf('Gross amount (%s) must equal net (%s) + VAT (%s) = %s', $this->gross, $this->net, $this->vat, $calculatedGross));
        }
    }
}
