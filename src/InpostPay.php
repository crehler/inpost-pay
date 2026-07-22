<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay;

use Crehler\InpostPay\Infrastructure\Lifecycle\Installer\{CustomFieldSetInstaller, PaymentMethodInstaller};
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\{ActivateContext, DeactivateContext, InstallContext, UninstallContext, UpdateContext};

class InpostPay extends Plugin
{
    public function getMigrationNamespace(): string
    {
        return 'Crehler\InpostPay\Infrastructure\Persistence\Migration';
    }

    public function install(InstallContext $installContext): void
    {
        $paymentMethodInstaller = new PaymentMethodInstaller(
            $this->container->get('payment_method.repository'),
            $this->container->get('Shopware\Core\Framework\Plugin\Util\PluginIdProvider'),
            $this->container->get('rule.repository'),
            $this->container->get('currency.repository')
        );
        $paymentMethodInstaller->install($installContext->getContext());

        $customFieldSetInstaller = new CustomFieldSetInstaller(
            $this->container->get('custom_field_set.repository'),
            $this->container->get('custom_field_set_relation.repository'),
            $this->container->get('custom_field.repository')
        );
        $customFieldSetInstaller->install($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $paymentMethodInstaller = new PaymentMethodInstaller(
            $this->container->get('payment_method.repository'),
            $this->container->get('Shopware\Core\Framework\Plugin\Util\PluginIdProvider'),
            $this->container->get('rule.repository'),
            $this->container->get('currency.repository')
        );
        $paymentMethodInstaller->uninstall($uninstallContext->getContext());

        $customFieldSetInstaller = new CustomFieldSetInstaller(
            $this->container->get('custom_field_set.repository'),
            $this->container->get('custom_field_set_relation.repository'),
            $this->container->get('custom_field.repository')
        );
        $customFieldSetInstaller->uninstall($uninstallContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        $paymentMethodInstaller = new PaymentMethodInstaller(
            $this->container->get('payment_method.repository'),
            $this->container->get('Shopware\Core\Framework\Plugin\Util\PluginIdProvider'),
            $this->container->get('rule.repository'),
            $this->container->get('currency.repository')
        );
        $paymentMethodInstaller->activate($activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $paymentMethodInstaller = new PaymentMethodInstaller(
            $this->container->get('payment_method.repository'),
            $this->container->get('Shopware\Core\Framework\Plugin\Util\PluginIdProvider'),
            $this->container->get('rule.repository'),
            $this->container->get('currency.repository')
        );
        $paymentMethodInstaller->deactivate($deactivateContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $paymentMethodInstaller = new PaymentMethodInstaller(
            $this->container->get('payment_method.repository'),
            $this->container->get('Shopware\Core\Framework\Plugin\Util\PluginIdProvider'),
            $this->container->get('rule.repository'),
            $this->container->get('currency.repository')
        );
        $paymentMethodInstaller->install($updateContext->getContext());

        if ($updateContext->getPlugin()->isActive()) {
            $paymentMethodInstaller->activate($updateContext->getContext());
        }

        $customFieldSetInstaller = new CustomFieldSetInstaller(
            $this->container->get('custom_field_set.repository'),
            $this->container->get('custom_field_set_relation.repository'),
            $this->container->get('custom_field.repository')
        );
        $customFieldSetInstaller->install($updateContext->getContext());
    }
}
