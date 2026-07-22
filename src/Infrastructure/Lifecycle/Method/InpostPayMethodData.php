<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Lifecycle\Method;

use Crehler\InpostPay\Infrastructure\Checkout\InpostPayPaymentHandler;

class InpostPayMethodData
{
    public function getHandler(): string
    {
        return InpostPayPaymentHandler::class;
    }

    public function getTechnicalName(): string
    {
        return 'payment_inpostpay';
    }

    public function getTranslations(): array
    {
        return [
            'de-DE' => [
                'name' => 'InPost Pay',
                'description' => 'Bezahlung mit InPost Pay - schnell und sicher',
            ],
            'en-GB' => [
                'name' => 'InPost Pay',
                'description' => 'Payment with InPost Pay - fast and secure',
            ],
            'pl-PL' => [
                'name' => 'InPost Pay',
                'description' => 'Płatność za pomocą InPost Pay - szybko i bezpiecznie',
            ],
        ];
    }

    public function getPosition(): int
    {
        return -90;
    }

    public function getMediaFileName(): ?string
    {
        return 'inpost-pay';
    }
}
