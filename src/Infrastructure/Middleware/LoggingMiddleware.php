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

use function array_is_list;
use function array_keys;
use function get_debug_type;
use function in_array;
use function is_array;
use function json_decode;
use function json_last_error;
use function microtime;
use function round;
use function strlen;
use function strtolower;

final readonly class LoggingMiddleware
{
    private const MAX_LOG_BODY_BYTES = 65536;

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

        $this->logger->debug('Sending HTTP request to InPost', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => $this->sanitizeHeaders($request->getHeaders()),
            'body' => $this->decodeBody($body),
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
            'body' => $this->decodeBody($body),
        ];

        if ($response->getStatusCode() >= 400) {
            $this->logger->error('Received an error response from InPost', $context);

            return;
        }

        $this->logger->debug('Received HTTP response from InPost', $context);
    }

    private function logTransportError(RequestInterface $request, Throwable $reason, float $startedAt): void
    {
        $this->logger->error('Transport error while calling InPost', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'error' => $reason->getMessage(),
            'exception_class' => $reason::class,
            'code' => $reason->getCode(),
        ]);
    }

    /**
     * @param array<string, array<int, string>> $headers
     *
     * @return array<string, array<int, string>|string>
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

    /**
     * Decodes a JSON body into an array so the logger's PII redaction can walk it. Records
     * only metadata - never the raw content - for oversized bodies, non-JSON bodies (e.g.
     * plain-text errors), and JSON that decodes to a scalar or list, since none of those are
     * something the recursive, key-name-based redactor can mask safely.
     */
    private function decodeBody(string $body): mixed
    {
        if ($body === '') {
            return '';
        }

        if (strlen($body) > self::MAX_LOG_BODY_BYTES) {
            return ['body_truncated' => true, 'body_size_bytes' => strlen($body)];
        }

        $decoded = json_decode($body, true);
        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            return ['invalid_json' => true, 'json_error' => $jsonError];
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            return ['payload_type' => get_debug_type($decoded)];
        }

        return $decoded;
    }
}
