<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Client;

use Crehler\InpostPay\Application\Dto\{TransactionQueryDto, TransactionResponseDto};
use Crehler\InpostPay\Domain\Exception\InpostPayEndpointException;
use Crehler\InpostPay\Domain\ValueObject\WidgetConfig;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\{ClientException, GuzzleException};
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

use function http_build_query;
use function is_array;
use function json_decode;
use function sprintf;

final readonly class InpostPayClient
{
    /**
     * @var string
     */
    private const URL_ACCESS_TOKEN_SANDBOX = 'https://sandbox-login.inpost.pl/auth/realms/external/protocol/openid-connect/token';
    /**
     * @var string
     */
    private const URL_ACCESS_TOKEN = 'https://login.inpost.pl/auth/realms/external/protocol/openid-connect/token';

    /**
     * @var string
     */
    private const API_BASE_URL_SANDBOX = 'https://sandbox-api.inpost.pl';
    /**
     * @var string
     */
    private const API_BASE_URL_PRODUCTION = 'https://api.inpost.pl';

    public function __construct(
        private ClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    public function getOAuth2Token(WidgetConfig $config): AccessToken
    {
        $provider = new GenericProvider([
            'clientId' => $config->clientId,
            'clientSecret' => $config->clientSecret,
            'urlAccessToken' => $this->getAccessTokenUrl($config->mode),
            'urlAuthorize' => null,
            'urlResourceOwnerDetails' => null,
        ]);

        try {
            return $provider->getAccessToken('client_credentials');
        } catch (IdentityProviderException|GuzzleException $e) {
            throw new InpostPayEndpointException('Failed to fetch OAuth2 token from InPost', previous: $e);
        }
    }

    public function bindBasket(string $basketId, string $bearerToken, string $mode = WidgetConfig::SANDBOX): string
    {
        try {
            $url = $this->getApiBaseUrl($mode) . "/v2/izi/basket/{$basketId}/binding";

            $response = $this->httpClient->request('PUT', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$bearerToken}",
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['basket_binding_api_key'])) {
                throw new InpostPayEndpointException('Invalid response from InPost API - missing basket_binding_api_key');
            }

            return $data['basket_binding_api_key'];
        } catch (GuzzleException $e) {
            throw new InpostPayEndpointException("Failed to bind basket: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }

    public function updateBasket(string $basketId, array $data, string $bearerToken, string $mode = WidgetConfig::SANDBOX): array
    {
        try {
            $url = $this->getApiBaseUrl($mode) . "/v2/izi/basket/{$basketId}";

            $response = $this->put($url, $data, $bearerToken);

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (GuzzleException $e) {
            throw new InpostPayEndpointException("Failed to update basket: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }

    public function get(string $url, string $bearerToken, array $options = []): ResponseInterface
    {
        return $this->request('GET', $url, $bearerToken, $options);
    }

    public function post(string $url, array $data, string $bearerToken, array $options = []): ResponseInterface
    {
        $options['json'] = $data;

        return $this->request('POST', $url, $bearerToken, $options);
    }

    public function put(string $url, array $data, string $bearerToken, array $options = []): ResponseInterface
    {
        $options['json'] = $data;

        return $this->request('PUT', $url, $bearerToken, $options);
    }

    public function patch(string $url, array $data, string $bearerToken, array $options = []): ResponseInterface
    {
        $options['json'] = $data;

        return $this->request('PATCH', $url, $bearerToken, $options);
    }

    public function delete(string $url, string $bearerToken, array $options = []): ResponseInterface
    {
        return $this->request('DELETE', $url, $bearerToken, $options);
    }

    public function deleteBasketBinding(
        string $basketId,
        string $bearerToken,
        string $mode = WidgetConfig::SANDBOX,
        ?bool $ifBasketRealized = null,
    ): void {
        try {
            $url = $this->getApiBaseUrl($mode) . "/v1/izi/basket/{$basketId}/binding";

            if ($ifBasketRealized !== null) {
                $url .= '?' . http_build_query(['if_basket_realized' => $ifBasketRealized]);
            }

            $response = $this->delete($url, $bearerToken);

            // 404 (BASKET_NOT_FOUND) means the binding is already gone on InPost's
            // side - deleting is idempotent, so treat it as success.
            if ($response->getStatusCode() === 404) {
                return;
            }

            if ($response->getStatusCode() !== 204) {
                throw new InpostPayEndpointException(sprintf('Unexpected status code %d when deleting basket binding', $response->getStatusCode()));
            }
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return;
            }

            throw new InpostPayEndpointException("Failed to delete basket binding: {$e->getMessage()}", (int) $e->getCode(), $e);
        } catch (GuzzleException $e) {
            throw new InpostPayEndpointException("Failed to delete basket binding: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }

    public function getTransactions(
        TransactionQueryDto $query,
        string $bearerToken,
        string $mode = WidgetConfig::SANDBOX,
    ): TransactionResponseDto {
        try {
            $url = $this->getApiBaseUrl($mode) . '/v1/izi/transaction';

            $response = $this->get($url, $bearerToken, [
                'query' => $query->toQueryParams(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return TransactionResponseDto::fromApiResponse($data);
        } catch (GuzzleException $e) {
            throw new InpostPayEndpointException("Failed to fetch transactions: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }

    public function fetchSigningKey(
        string $version,
        string $bearerToken,
        string $mode = WidgetConfig::SANDBOX,
    ): array {
        try {
            $url = $this->getApiBaseUrl($mode) . "/v1/izi/signing-keys/public/{$version}";

            $response = $this->get($url, $bearerToken);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!is_array($data)) {
                throw new InpostPayEndpointException('Invalid response from signing-keys endpoint');
            }

            return $data;
        } catch (GuzzleException $e) {
            throw new InpostPayEndpointException("Failed to fetch signing key: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }

    public function sendOrderEvent(
        string $orderId,
        array $data,
        string $bearerToken,
        string $mode = WidgetConfig::SANDBOX,
    ): array {
        try {
            $url = $this->getApiBaseUrl($mode) . "/v1/izi/order/{$orderId}/event";

            $response = $this->post($url, $data, $bearerToken);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() >= 400) {
                throw new InpostPayEndpointException(sprintf('Order event request failed with status %d: %s', $response->getStatusCode(), $responseData['message'] ?? 'Unknown error'), $response->getStatusCode());
            }

            return $responseData ?? [];
        } catch (GuzzleException $e) {
            throw new InpostPayEndpointException("Failed to send order event: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }

    public function requestRefund(
        string $transactionId,
        string $commandId,
        array $data,
        string $bearerToken,
        string $mode = WidgetConfig::SANDBOX,
    ): array {
        try {
            $url = $this->getApiBaseUrl($mode) . "/v1/izi/transaction/{$transactionId}/refund";

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$bearerToken}",
                    'Content-Type' => 'application/json',
                    'X-Command-ID' => $commandId,
                ],
                'json' => $data,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() >= 400) {
                throw new InpostPayEndpointException(sprintf('Refund request failed with status %d: %s', $response->getStatusCode(), $responseData['message'] ?? 'Unknown error'), $response->getStatusCode());
            }

            return $responseData ?? [];
        } catch (GuzzleException $e) {
            throw new InpostPayEndpointException("Failed to request refund: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }

    private function request(string $method, string $url, string $bearerToken, array $options = []): ResponseInterface
    {
        $options['headers'] ??= [];
        $options['headers']['Authorization'] = 'Bearer ' . $bearerToken;

        return $this->httpClient->request($method, $url, $options);
    }

    private function getAccessTokenUrl(string $mode): string
    {
        return match ($mode) {
            WidgetConfig::PRODUCTION => self::URL_ACCESS_TOKEN,
            WidgetConfig::SANDBOX => self::URL_ACCESS_TOKEN_SANDBOX,
        };
    }

    private function getApiBaseUrl(string $mode): string
    {
        return match ($mode) {
            WidgetConfig::PRODUCTION => self::API_BASE_URL_PRODUCTION,
            WidgetConfig::SANDBOX => self::API_BASE_URL_SANDBOX,
        };
    }
}
