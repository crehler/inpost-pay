<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Service;

use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

use function rtrim;

final class ProductUrlGenerator
{
    public function __construct(
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlHandler,
        private readonly EntityRepository $salesChannelDomainRepository,
    ) {
    }

    public function generateUrl(?string $productId, SalesChannelContext $context): ?string
    {
        if ($productId === null) {
            return null;
        }

        $baseUrl = $this->resolveBaseUrl($context);
        if ($baseUrl === null) {
            return null;
        }

        $seoUrl = $this->seoUrlHandler->generate(
            'frontend.detail.page',
            ['productId' => $productId]
        );

        return $this->seoUrlHandler->replace($seoUrl, $baseUrl, $context);
    }

    /**
     * Generate URL from Context and salesChannelId (for use in OrderDataExtractor where SalesChannelContext is not available).
     */
    public function generateUrlFromContext(?string $productId, Context $context, string $salesChannelId): ?string
    {
        if ($productId === null) {
            return null;
        }

        $baseUrl = $this->loadDomainUrlBySalesChannelId($salesChannelId, $context);
        if ($baseUrl === null) {
            return null;
        }

        return $baseUrl . '/detail/' . $productId;
    }

    private function resolveBaseUrl(SalesChannelContext $context): ?string
    {
        $domains = $context->getSalesChannel()->getDomains();

        if ($domains !== null && $domains->count() > 0) {
            $languageId = $context->getContext()->getLanguageId();
            $domain = $domains->filterByProperty('languageId', $languageId)->first();

            if ($domain !== null) {
                return rtrim($domain->getUrl(), '/');
            }

            $firstDomain = $domains->first();
            if ($firstDomain !== null) {
                return rtrim($firstDomain->getUrl(), '/');
            }
        }

        return $this->loadDomainFromDatabase($context);
    }

    private function loadDomainFromDatabase(SalesChannelContext $context): ?string
    {
        $salesChannelId = $context->getSalesChannel()->getId();
        $languageId = $context->getContext()->getLanguageId();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('languageId', $languageId));
        $criteria->setLimit(1);

        $domain = $this->salesChannelDomainRepository
            ->search($criteria, $context->getContext())
            ->first();

        if ($domain !== null) {
            return rtrim($domain->getUrl(), '/');
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->setLimit(1);

        $domain = $this->salesChannelDomainRepository
            ->search($criteria, $context->getContext())
            ->first();

        return $domain?->getUrl() !== null ? rtrim($domain->getUrl(), '/') : null;
    }

    private function loadDomainUrlBySalesChannelId(string $salesChannelId, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->setLimit(1);

        $domain = $this->salesChannelDomainRepository
            ->search($criteria, $context)
            ->first();

        return $domain?->getUrl() !== null ? rtrim($domain->getUrl(), '/') : null;
    }
}
