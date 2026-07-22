<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Domain\ValueObject\Address;
use Crehler\InpostPay\Domain\ValueObject\Order\CustomerInfo;
use DateTimeImmutable;
use RuntimeException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

use function implode;
use function sprintf;
use function strtoupper;
use function trim;

readonly class CustomerMatchingService
{
    public function __construct(
        #[Target('customer.repository')]
        private EntityRepository $customerRepository,
        #[Target('country.repository')]
        private EntityRepository $countryRepository,
        #[Target('sales_channel.repository')]
        private EntityRepository $salesChannelRepository,
        #[Target('customer_address.repository')]
        private EntityRepository $customerAddressRepository,
        private NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
    ) {
    }

    public function findOrCreateCustomer(
        CustomerInfo $customerInfo,
        Address $billingAddress,
        string $salesChannelId,
        Context $context,
    ): CustomerEntity {
        $existingCustomer = $this->findCustomerByEmail(
            $customerInfo->email,
            $salesChannelId,
            $context
        );

        if ($existingCustomer !== null) {
            return $existingCustomer;
        }

        return $this->createGuestCustomer(
            $customerInfo,
            $billingAddress,
            $salesChannelId,
            $context
        );
    }

    /**
     * @return array{customer: CustomerEntity, billingAddressId: string, shippingAddressId: string}
     */
    public function findOrCreateCustomerWithAddress(
        CustomerInfo $customerInfo,
        Address $billingAddress,
        string $salesChannelId,
        Context $context,
    ): array {
        $existingCustomer = $this->findCustomerByEmail(
            $customerInfo->email,
            $salesChannelId,
            $context
        );

        if ($existingCustomer !== null) {
            $addressId = $this->findOrCreateAddressForCustomer(
                $existingCustomer,
                $customerInfo,
                $billingAddress,
                $context
            );

            return [
                'customer' => $existingCustomer,
                'billingAddressId' => $addressId,
                'shippingAddressId' => $addressId,
            ];
        }

        $guest = $this->createGuestCustomer(
            $customerInfo,
            $billingAddress,
            $salesChannelId,
            $context
        );

        return [
            'customer' => $guest,
            'billingAddressId' => $guest->getDefaultBillingAddressId(),
            'shippingAddressId' => $guest->getDefaultShippingAddressId(),
        ];
    }

    public function findOrCreateAddressForCustomer(
        CustomerEntity $customer,
        CustomerInfo $customerInfo,
        Address $inpostAddress,
        Context $context,
    ): string {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));

        $addresses = $this->customerAddressRepository->search($criteria, $context);
        $street = $this->buildStreetLine($inpostAddress);
        $phoneNumber = $customerInfo->phone->getFullNumber();

        foreach ($addresses->getEntities() as $address) {
            if ($this->addressMatches($address, $inpostAddress, $street, $phoneNumber)) {
                return $address->getId();
            }
        }

        return $this->createAddressForCustomer(
            $customer,
            $customerInfo,
            $inpostAddress,
            $context
        );
    }

    public function getAddressById(string $addressId, Context $context): ?CustomerAddressEntity
    {
        $criteria = new Criteria([$addressId]);

        return $this->customerAddressRepository->search($criteria, $context)->first();
    }

    public function findCustomerByEmail(
        string $email,
        string $salesChannelId,
        Context $context,
    ): ?CustomerEntity {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('guest', false));
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addAssociation('defaultShippingAddress');
        $criteria->setLimit(10);

        $result = $this->customerRepository->search($criteria, $context);

        foreach ($result->getEntities() as $customer) {
            $boundSalesChannelId = $customer->getBoundSalesChannelId();

            if ($boundSalesChannelId === null || $boundSalesChannelId === $salesChannelId) {
                return $customer;
            }
        }

        return null;
    }

    public function createGuestCustomer(
        CustomerInfo $customerInfo,
        Address $billingAddress,
        string $salesChannelId,
        Context $context,
    ): CustomerEntity {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $countryId = $this->resolveCountryId($billingAddress->countryCode, $context);

        $street = $this->buildStreetLine($billingAddress);

        $customerData = [
            'id' => $customerId,
            'salesChannelId' => $salesChannelId,
            'groupId' => $this->getDefaultCustomerGroupId($salesChannelId, $context),
            'defaultPaymentMethodId' => $this->getDefaultPaymentMethodId($salesChannelId, $context),
            'languageId' => $context->getLanguageId(),
            'customerNumber' => $this->numberRangeValueGenerator->getValue(
                'customer',
                $context,
                $salesChannelId
            ),
            'firstName' => $customerInfo->firstName,
            'lastName' => $customerInfo->lastName,
            'email' => $customerInfo->email,
            'active' => true,
            'guest' => true,
            'firstLogin' => new DateTimeImmutable(),
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $countryId,
                    'firstName' => $customerInfo->firstName,
                    'lastName' => $customerInfo->lastName,
                    'street' => $street,
                    'zipcode' => $billingAddress->postalCode,
                    'city' => $billingAddress->city,
                    'phoneNumber' => $customerInfo->phone->getFullNumber(),
                ],
            ],
        ];

        $this->customerRepository->create([$customerData], $context);

        $criteria = new Criteria([$customerId]);
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addAssociation('defaultShippingAddress');

        $customer = $this->customerRepository->search($criteria, $context)->first();

        return $customer;
    }

    public function resolveCountryId(string $countryCode, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', strtoupper($countryCode)));
        $criteria->setLimit(1);

        $result = $this->countryRepository->searchIds($criteria, $context);

        if ($result->firstId() === null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('iso', 'PL'));
            $criteria->setLimit(1);

            $result = $this->countryRepository->searchIds($criteria, $context);
        }

        $countryId = $result->firstId();

        if ($countryId === null) {
            throw new RuntimeException(sprintf('Country not found for ISO code: %s', $countryCode));
        }

        return $countryId;
    }

    private function addressMatches(
        CustomerAddressEntity $existing,
        Address $inpostAddress,
        string $street,
        string $phoneNumber,
    ): bool {
        return $existing->getStreet() === $street
            && $existing->getZipcode() === $inpostAddress->postalCode
            && $existing->getCity() === $inpostAddress->city
            && $existing->getPhoneNumber() === $phoneNumber
            && $existing->getAdditionalAddressLine1() === $inpostAddress->additionalAddressLine1;
    }

    private function createAddressForCustomer(
        CustomerEntity $customer,
        CustomerInfo $customerInfo,
        Address $inpostAddress,
        Context $context,
    ): string {
        $addressId = Uuid::randomHex();
        $countryId = $this->resolveCountryId($inpostAddress->countryCode, $context);
        $street = $this->buildStreetLine($inpostAddress);

        $addressData = [
            'id' => $addressId,
            'customerId' => $customer->getId(),
            'countryId' => $countryId,
            'firstName' => $customerInfo->firstName,
            'lastName' => $customerInfo->lastName,
            'street' => $street,
            'zipcode' => $inpostAddress->postalCode,
            'city' => $inpostAddress->city,
            'phoneNumber' => $customerInfo->phone->getFullNumber(),
            'additionalAddressLine1' => $inpostAddress->additionalAddressLine1,
        ];

        $this->customerAddressRepository->create([$addressData], $context);

        return $addressId;
    }

    private function buildStreetLine(Address $address): string
    {
        // Prefer InPost's free-text address line: address_details.building drops hyphens
        // (e.g. "11-13" -> "1113"), the free-text line keeps the value intact.
        if (trim($address->streetLine) !== '') {
            return $address->streetLine;
        }

        if ($address->details !== null) {
            $parts = [];

            if ($address->details->street) {
                $parts[] = $address->details->street;
            }

            if ($address->details->building) {
                $parts[] = $address->details->building;
            }

            if ($address->details->flat) {
                $parts[] = '/' . $address->details->flat;
            }

            if (!empty($parts)) {
                return implode(' ', $parts);
            }
        }

        return $address->streetLine;
    }

    private function getDefaultCustomerGroupId(string $salesChannelId, Context $context): string
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('customerGroup');

        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->first();

        if ($salesChannel === null || $salesChannel->getCustomerGroupId() === null) {
            throw new RuntimeException(sprintf('Sales channel %s not found or has no customer group', $salesChannelId));
        }

        return $salesChannel->getCustomerGroupId();
    }

    private function getDefaultPaymentMethodId(string $salesChannelId, Context $context): string
    {
        $criteria = new Criteria([$salesChannelId]);

        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->first();

        if ($salesChannel === null || $salesChannel->getPaymentMethodId() === null) {
            throw new RuntimeException(sprintf('Sales channel %s not found or has no payment method', $salesChannelId));
        }

        return $salesChannel->getPaymentMethodId();
    }
}
