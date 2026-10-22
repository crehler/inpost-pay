<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Logger;

use Symfony\Component\HttpFoundation\Request;

use function array_filter;
use function array_is_list;
use function get_debug_type;
use function implode;
use function in_array;
use function is_array;
use function json_decode;
use function json_last_error;
use function strlen;
use function strtolower;

/**
 * Builds the log context for an incoming InPost request, so IncomingRequestLoggerSubscriber
 * is left with what a subscriber should do: pick the request and delegate.
 */
final readonly class IncomingRequestLogFormatter
{
    /**
     * Header values replaced with a marker. The name still shows in the log, so it stays
     * visible that a request was signed or carried a key - only the value is withheld.
     * sw-context-token is on the list because it identifies the shopper's Shopware cart
     * session, which is as good as a bearer token for that cart.
     */
    private const MASKED_HEADERS = [
        'authorization',
        'cookie',
        'proxy-authorization',
        'sw-context-token',
        'x-access-token',
        'x-api-key',
        'x-auth-token',
        'x-signature',
    ];

    /**
     * Everything about the request except its body - safe to write for any caller, because
     * none of it is attacker-supplied free text.
     *
     * @return array<string, mixed>
     */
    public function metadata(Request $request): array
    {
        return [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'route' => $request->attributes->get('_route'),
            'query' => $this->collectQuery($request),
            'headers' => $this->collectHeaders($request),
            'payload_size' => strlen($request->getContent()),
        ];
    }

    /**
     * Logged separately and only once the signature has been verified. The path repeats here
     * so the entry can be tied back to the metadata written earlier in the same request.
     *
     * @return array<string, mixed>
     */
    public function body(Request $request): array
    {
        return [
            'path' => $request->getPathInfo(),
            'body' => $this->decodeBody($request->getContent()),
        ];
    }

    /**
     * Not one of our InPost endpoints reads a query parameter - they take their input from
     * the path and the body - so a value in the query string is something the caller
     * appended, and this runs before any signature check. The names are kept, because they
     * still record what was attempted; the values are never written.
     *
     * @return array<string, string>
     */
    private function collectQuery(Request $request): array
    {
        $query = [];

        foreach ($request->query->keys() as $name) {
            $query[$name] = '[REDACTED]';
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    private function collectHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = in_array(strtolower($name), self::MASKED_HEADERS, true)
                ? '[REDACTED]'
                : implode(', ', array_filter($values, static fn (?string $value): bool => $value !== null));
        }

        return $headers;
    }

    /**
     * Decodes a JSON body so the logger's PII redaction can walk it; a body that is not a
     * JSON object is reduced to a marker, because the redactor cannot mask scalars or lists.
     */
    private function decodeBody(string $body): mixed
    {
        if ($body === '') {
            return '';
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['invalid_json' => true, 'raw_size' => strlen($body)];
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            return ['payload_type' => get_debug_type($decoded)];
        }

        return $decoded;
    }
}
