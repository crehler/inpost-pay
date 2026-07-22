<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Middleware;

use Closure;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Ramsey\Uuid\Uuid;

final class HeadersMiddleware
{
    private Closure $middleware;

    public function __construct()
    {
        $this->middleware = Middleware::mapRequest(function (RequestInterface $request): RequestInterface {
            return $request
                ->withHeader('User-Agent', 'InpostPay-Shopware/1.0')
                ->withHeader('X-Request-ID', Uuid::uuid4()->toString())
                ->withHeader('Accept', 'application/json');
        });
    }

    public function __invoke(callable $handler): callable
    {
        $middleware = $this->middleware;

        return $middleware($handler);
    }
}
