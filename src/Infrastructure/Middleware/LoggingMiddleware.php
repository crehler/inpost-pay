<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Middleware;

use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Psr\Log\LoggerInterface;
use Throwable;

use function array_keys;
use function in_array;
use function mb_strlen;
use function mb_substr;
use function microtime;
use function round;
use function strtolower;

final readonly class LoggingMiddleware
{
    private const BODY_MAX_LENGTH = 4096;

    private const REDACTED_HEADERS = ['authorization', 'x-api-key', 'cookie', 'set-cookie'];

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $startedAt = microtime(true);

            $this->logRequest($request);

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($request, $startedAt) {
                    $this->logResponse($request, $response, $startedAt);

                    return $response;
                },
                function (Throwable $reason) use ($request, $startedAt) {
                    $this->logTransportError($request, $reason, $startedAt);

                    throw $reason;
                }
            );
        };
    }

    private function logRequest(RequestInterface $request): void
    {
        $body = (string) $request->getBody();
        if ($request->getBody()->isSeekable()) {
            $request->getBody()->rewind();
        }

        $this->logger->info('InPost HTTP request', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => $this->sanitizeHeaders($request->getHeaders()),
            'body' => $this->truncate($body),
        ]);
    }

    private function logResponse(RequestInterface $request, ResponseInterface $response, float $startedAt): void
    {
        $body = (string) $response->getBody();
        if ($response->getBody()->isSeekable()) {
            $response->getBody()->rewind();
        }

        $context = [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'status' => $response->getStatusCode(),
            'reason' => $response->getReasonPhrase(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'body' => $this->truncate($body),
        ];

        if ($response->getStatusCode() >= 400) {
            $this->logger->error('InPost HTTP response error', $context);

            return;
        }

        $this->logger->info('InPost HTTP response', $context);
    }

    private function logTransportError(RequestInterface $request, Throwable $reason, float $startedAt): void
    {
        $this->logger->error('InPost HTTP transport error', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'error' => $reason->getMessage(),
            'exception_class' => $reason::class,
            'code' => $reason->getCode(),
        ]);
    }

    /**
     * @param array<string, list<string>> $headers
     *
     * @return array<string, list<string>|string>
     */
    private function sanitizeHeaders(array $headers): array
    {
        foreach (array_keys($headers) as $name) {
            if (in_array(strtolower((string) $name), self::REDACTED_HEADERS, true)) {
                $headers[$name] = '[REDACTED]';
            }
        }

        return $headers;
    }

    private function truncate(string $body): string
    {
        if ($body === '') {
            return '';
        }

        if (mb_strlen($body) <= self::BODY_MAX_LENGTH) {
            return $body;
        }

        return mb_substr($body, 0, self::BODY_MAX_LENGTH) . '... [truncated]';
    }
}
