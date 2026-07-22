<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Domain\ValueObject\ServiceCode;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ServiceNameTranslator
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function getName(ServiceCode $serviceCode): string
    {
        return $this->translator->trans($serviceCode->getDisplayNameKey());
    }
}
