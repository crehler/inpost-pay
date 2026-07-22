<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Service;

use Crehler\InpostPay\Domain\ValueObject\Consent\{ConsentConfiguration, ConsentLinkType};
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class ConsentLinkResolver
{
    public function __construct(
        private SeoUrlPlaceholderHandlerInterface $seoUrlHandler,
        private EntityRepository $categoryRepository,
        private EntityRepository $cmsPageRepository,
        private EntityRepository $salesChannelDomainRepository,
    ) {
    }

    public function resolveLink(
        ConsentConfiguration $config,
        SalesChannelContext $context,
    ): string {
        return match ($config->linkType) {
            ConsentLinkType::CMS_PAGE => $this->resolveCmsPageLink($config->cmsPageId, $context),
            ConsentLinkType::CATEGORY => $this->resolveCategoryLink($config->cmsPageId, $context),
            ConsentLinkType::EXTERNAL => $config->externalLink ?? '',
        };
    }

    private function resolveCmsPageLink(?string $cmsPageId, SalesChannelContext $context): string
    {
        if ($cmsPageId === null || $cmsPageId === '') {
            return '';
        }

        $criteria = new Criteria([$cmsPageId]);
        $cmsPage = $this->cmsPageRepository->search($criteria, $context->getContext())->first();

        if ($cmsPage === null) {
            return '';
        }

        $baseUrl = $this->getBaseUrl($context);

        return $baseUrl . '/widgets/cms/' . $cmsPageId;
    }

    private function resolveCategoryLink(?string $categoryId, SalesChannelContext $context): string
    {
        if ($categoryId === null || $categoryId === '') {
            return '';
        }

        $baseUrl = $this->getBaseUrl($context);

        $seoUrl = $this->seoUrlHandler->generate(
            'frontend.navigation.page',
            ['navigationId' => $categoryId]
        );

        return $this->seoUrlHandler->replace($seoUrl, $baseUrl, $context);
    }

    private function getBaseUrl(SalesChannelContext $context): string
    {
        $domains = $context->getSalesChannel()->getDomains();

        if ($domains !== null && $domains->count() > 0) {
            foreach ($domains as $domain) {
                if ($domain->getLanguageId() === $context->getLanguageId()) {
                    return $domain->getUrl() ?? '';
                }
            }

            return $domains->first()?->getUrl() ?? '';
        }

        return $this->loadDomainUrlFromDatabase($context);
    }

    private function loadDomainUrlFromDatabase(SalesChannelContext $context): string
    {
        $salesChannelId = $context->getSalesChannel()->getId();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('languageId', $context->getLanguageId()));
        $criteria->setLimit(1);

        $domain = $this->salesChannelDomainRepository->search($criteria, $context->getContext())->first();

        if ($domain !== null) {
            return $domain->getUrl() ?? '';
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->setLimit(1);

        $domain = $this->salesChannelDomainRepository->search($criteria, $context->getContext())->first();

        return $domain?->getUrl() ?? '';
    }
}
