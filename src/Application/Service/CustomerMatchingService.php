<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Application\Event\InpostAddressMappedEvent;
use Crehler\InpostPay\Domain\ValueObject\Address;
use Crehler\InpostPay\Domain\ValueObject\Order\CustomerInfo;
use DateTimeImmutable;
use Psr\EventDispatcher\EventDispatcherInterface;
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

use function array_merge;
use function implode;
use function in_array;
use function sprintf;
use function strtoupper;

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
        private EventDispatcherInterface $eventDispatcher,
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
        $mappedAddress = $this->mapInpostAddress($inpostAddress, $customerInfo, $context);

        foreach ($addresses->getEntities() as $address) {
            if ($this->addressMatches($address, $mappedAddress)) {
                return $address->getId();
            }
        }

        return $this->createAddressForCustomer($customer, $mappedAddress, $context);
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

        $mappedAddress = $this->mapInpostAddress($billingAddress, $customerInfo, $context);

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
                array_merge($mappedAddress, [
                    'id' => $addressId,
                    'customerId' => $customerId,
                ]),
            ],
        ];

        $this->customerRepository->create([$customerData], $context);

        $criteria = new Criteria([$customerId]);
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addAssociation('defaultShippingAddress');

        $customer = $this->customerRepository->search($criteria, $context)->first();

        return $customer;
    }

    public function addVatIdToCustomer(CustomerEntity $customer, string $vatId, Context $context): void
    {
        // Przekazana encja mogła zostać pobrana dużo wcześniej (początek createOrder) —
        // odczytaj vatIds świeżo tuż przed zapisem, żeby nie nadpisać cudzych zmian.
        $freshCustomer = $this->customerRepository
            ->search(new Criteria([$customer->getId()]), $context)
            ->first();

        $vatIds = $freshCustomer?->getVatIds() ?? [];

        if (in_array($vatId, $vatIds, true)) {
            return;
        }

        $vatIds[] = $vatId;

        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'vatIds' => $vatIds,
            ],
        ], $context);
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

    /**
     * Wynik (po ewentualnych korektach listenerów InpostAddressMappedEvent) służy
     * zarówno do zapisu adresu, jak i do deduplikacji — musi pozostać spójny.
     *
     * @return array<string, mixed>
     */
    private function mapInpostAddress(Address $inpostAddress, CustomerInfo $customerInfo, Context $context): array
    {
        $addressData = [
            'countryId' => $this->resolveCountryId($inpostAddress->countryCode, $context),
            'firstName' => $customerInfo->firstName,
            'lastName' => $customerInfo->lastName,
            'street' => $this->buildStreetLine($inpostAddress),
            'zipcode' => $inpostAddress->postalCode,
            'city' => $inpostAddress->city,
            'phoneNumber' => $customerInfo->phone->getFullNumber(),
            'additionalAddressLine1' => $inpostAddress->additionalAddressLine1,
        ];

        $event = new InpostAddressMappedEvent($inpostAddress, $addressData);
        $this->eventDispatcher->dispatch($event);

        return $event->getAddressData();
    }

    /**
     * @param array<string, mixed> $mappedAddress
     */
    private function addressMatches(CustomerAddressEntity $existing, array $mappedAddress): bool
    {
        return $existing->getCountryId() === ($mappedAddress['countryId'] ?? null)
            && $existing->getFirstName() === ($mappedAddress['firstName'] ?? null)
            && $existing->getLastName() === ($mappedAddress['lastName'] ?? null)
            && $existing->getStreet() === ($mappedAddress['street'] ?? null)
            && $existing->getZipcode() === ($mappedAddress['zipcode'] ?? null)
            && $existing->getCity() === ($mappedAddress['city'] ?? null)
            && $existing->getPhoneNumber() === ($mappedAddress['phoneNumber'] ?? null)
            && $existing->getAdditionalAddressLine1() === ($mappedAddress['additionalAddressLine1'] ?? null);
    }

    /**
     * @param array<string, mixed> $mappedAddress
     */
    private function createAddressForCustomer(
        CustomerEntity $customer,
        array $mappedAddress,
        Context $context,
    ): string {
        $addressId = Uuid::randomHex();

        $addressData = array_merge($mappedAddress, [
            'id' => $addressId,
            'customerId' => $customer->getId(),
        ]);

        $this->customerAddressRepository->create([$addressData], $context);

        return $addressId;
    }

    private function buildStreetLine(Address $address): string
    {
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
