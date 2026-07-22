<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Serializer;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use UnexpectedValueException;

use function is_array;
use function json_decode;
use function sprintf;

final readonly class RequestDeserializer
{
    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    public function decodeRequest(Request $request): array
    {
        try {
            $content = $request->getContent();

            if (empty($content)) {
                throw new InvalidArgumentException('Request body is empty');
            }

            $data = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                throw new UnexpectedValueException('Expected array data from JSON');
            }

            return $data;
        } catch (InvalidArgumentException|UnexpectedValueException $e) {
            throw $e;
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Failed to decode request JSON: %s', $e->getMessage()), 0, $e);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to decode request: %s', $e->getMessage()), 0, $e);
        }
    }
}
