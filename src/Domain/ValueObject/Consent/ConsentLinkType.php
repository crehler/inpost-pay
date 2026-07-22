<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject\Consent;

enum ConsentLinkType: string
{
    case CMS_PAGE = 'cms_page';
    case CATEGORY = 'category';
    case EXTERNAL = 'external';

    public function isCmsPage(): bool
    {
        return $this === self::CMS_PAGE;
    }

    public function isCategory(): bool
    {
        return $this === self::CATEGORY;
    }

    public function isExternal(): bool
    {
        return $this === self::EXTERNAL;
    }
}
