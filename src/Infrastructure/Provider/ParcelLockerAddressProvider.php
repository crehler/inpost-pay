<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Provider;

use Crehler\InpostPay\Domain\ValueObject\Address;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function rawurlencode;
use function sprintf;
use function trim;

/**
 * Resolves an InPost parcel locker (Paczkomat) physical address from its point
 * code via the public ShipX Points API. The InPost Pay order payload only
 * carries the locker code (delivery_point) for APM deliveries, never the
 * address, so it must be fetched here.
 */
readonly class ParcelLockerAddressProvider
{
    private const POINT_URL = 'https://api-shipx-pl.easypack24.net/v1/points/%s';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    public function resolveAddress(string $pointName): ?Address
    {
        try {
            $response = $this->httpClient->request('GET', sprintf(self::POINT_URL, rawurlencode($pointName)));

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Failed to resolve InPost parcel locker address', [
                    'point' => $pointName,
                    'status' => $response->getStatusCode(),
                ]);

                return null;
            }

            $data = $response->toArray(false);
        } catch (Throwable $e) {
            $this->logger->error('Error resolving InPost parcel locker address', [
                'point' => $pointName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $details = $data['address_details'] ?? [];
        $city = (string) ($details['city'] ?? '');
        $postalCode = (string) ($details['post_code'] ?? '');
        $street = trim(sprintf('%s %s', (string) ($details['street'] ?? ''), (string) ($details['building_number'] ?? '')));

        if ($street === '') {
            $street = (string) ($data['address']['line1'] ?? '');
        }

        if ($city === '' || $postalCode === '' || $street === '') {
            $this->logger->error('Incomplete InPost parcel locker address data', [
                'point' => $pointName,
            ]);

            return null;
        }

        $label = (string) ($data['display_name'] ?? sprintf('Paczkomat %s', $pointName));

        return new Address(
            countryCode: 'PL',
            city: $city,
            postalCode: $postalCode,
            streetLine: $street,
            additionalAddressLine1: $label,
        );
    }
}
