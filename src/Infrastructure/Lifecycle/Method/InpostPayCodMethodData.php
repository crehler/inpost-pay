<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Lifecycle\Method;

use Crehler\InpostPay\Infrastructure\Checkout\InpostPayCodPaymentHandler;

class InpostPayCodMethodData extends InpostPayMethodData
{
    public function getHandler(): string
    {
        return InpostPayCodPaymentHandler::class;
    }

    public function getTechnicalName(): string
    {
        return 'payment_inpostpay_cod';
    }

    public function getTranslations(): array
    {
        return [
            'de-DE' => [
                'name' => 'InPost Pay - Nachnahme',
                'description' => 'Bezahlung mit InPost Pay - Nachnahme',
            ],
            'en-GB' => [
                'name' => 'InPost Pay - Cash on Delivery',
                'description' => 'Payment with InPost Pay - cash on delivery',
            ],
            'pl-PL' => [
                'name' => 'InPost Pay - pobranie',
                'description' => 'Płatność za pomocą InPost Pay - płatność przy odbiorze',
            ],
        ];
    }

    public function getPosition(): int
    {
        return -89;
    }
}
