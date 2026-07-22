<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Logger;

use Psr\Log\LoggerInterface;
use Throwable;

use function array_merge;

final readonly class ExceptionLogger
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function error(string $message, Throwable $exception, array $context = []): void
    {
        $this->log('error', $message, $exception, $context);
    }

    public function warning(string $message, Throwable $exception, array $context = []): void
    {
        $this->log('warning', $message, $exception, $context);
    }

    private function log(string $level, string $message, Throwable $exception, array $context): void
    {
        $exceptionContext = [
            'error' => $exception->getMessage(),
            'exception_class' => $exception::class,
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        if ($exception->getPrevious() !== null) {
            $exceptionContext['previous'] = [
                'error' => $exception->getPrevious()->getMessage(),
                'exception_class' => $exception->getPrevious()::class,
                'code' => $exception->getPrevious()->getCode(),
            ];
        }

        $this->logger->{$level}($message, array_merge($context, $exceptionContext));
    }
}
