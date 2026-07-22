<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Tests\Infrastructure\Provider;

use Crehler\InpostPay\Infrastructure\Provider\ParcelLockerAddressProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ParcelLockerAddressProviderTest extends TestCase
{
    public function testResolvesPhysicalAddressFromPointCode(): void
    {
        $body = json_encode([
            'name' => 'POZ01H',
            'display_name' => 'InPost Paczkomat POZ01H',
            'address' => ['line1' => 'Zwierzyniecka 28', 'line2' => '60-814 Poznań'],
            'address_details' => [
                'city' => 'Poznań',
                'post_code' => '60-814',
                'street' => 'Zwierzyniecka',
                'building_number' => '28',
            ],
        ]);

        $provider = new ParcelLockerAddressProvider(
            new MockHttpClient(new MockResponse($body)),
            new NullLogger(),
        );

        $address = $provider->resolveAddress('POZ01H');

        self::assertNotNull($address);
        self::assertSame('PL', $address->countryCode);
        self::assertSame('Poznań', $address->city);
        self::assertSame('60-814', $address->postalCode);
        self::assertSame('Zwierzyniecka 28', $address->streetLine);
        self::assertSame('InPost Paczkomat POZ01H', $address->additionalAddressLine1);
    }

    public function testReturnsNullOnNon200Response(): void
    {
        $provider = new ParcelLockerAddressProvider(
            new MockHttpClient(new MockResponse('', ['http_code' => 404])),
            new NullLogger(),
        );

        self::assertNull($provider->resolveAddress('NOPE99'));
    }

    public function testReturnsNullOnIncompleteAddressData(): void
    {
        $body = json_encode([
            'name' => 'POZ01H',
            'address_details' => ['city' => 'Poznań'],
        ]);

        $provider = new ParcelLockerAddressProvider(
            new MockHttpClient(new MockResponse($body)),
            new NullLogger(),
        );

        self::assertNull($provider->resolveAddress('POZ01H'));
    }

    public function testFallsBackToAddressLine1WhenDetailsMissingStreet(): void
    {
        $body = json_encode([
            'name' => 'POZ01H',
            'address' => ['line1' => 'Zwierzyniecka 28', 'line2' => '60-814 Poznań'],
            'address_details' => [
                'city' => 'Poznań',
                'post_code' => '60-814',
            ],
        ]);

        $provider = new ParcelLockerAddressProvider(
            new MockHttpClient(new MockResponse($body)),
            new NullLogger(),
        );

        $address = $provider->resolveAddress('POZ01H');

        self::assertNotNull($address);
        self::assertSame('Zwierzyniecka 28', $address->streetLine);
        self::assertSame('Paczkomat POZ01H', $address->additionalAddressLine1);
    }
}
