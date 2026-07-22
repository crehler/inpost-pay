<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Exception;

use Exception;
use Throwable;

use function sprintf;

final class InpostPayEndpointException extends Exception
{
    final public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function createClientException(Throwable $previous): self
    {
        return new self(
            sprintf('InpostPayClient, error occurred when creating http client with bearer token. Exception message: %s', $throwable->getMessage()),
            $throwable->getCode(),
            $throwable
        );
    }
}
