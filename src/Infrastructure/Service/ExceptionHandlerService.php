<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Service;

use Crehler\InpostPay\Domain\Exception\{BasketNotFoundException, BasketSessionNotFoundException, InvalidBasketException, InvalidOrderEventException, OrderNotFoundException};
use Crehler\InpostPay\Infrastructure\Api\Builder\InpostApiResponseBuilder;
use Crehler\InpostPay\Infrastructure\Logger\ExceptionLogger;
use DomainException;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\HttpFoundation\{JsonResponse, Response};
use Throwable;

use function sprintf;

readonly class ExceptionHandlerService
{
    public function __construct(
        private InpostApiResponseBuilder $responseBuilder,
        private ExceptionLogger $exceptionLogger,
    ) {
    }

    public function handle(
        Throwable $exception,
        string $operation,
        ?string $resourceId = null,
    ): JsonResponse {
        return match (true) {
            $exception instanceof BasketNotFoundException => $this->handleNotFound($exception, $operation, $resourceId),
            $exception instanceof OrderNotFoundException => $this->handleNotFound($exception, $operation, $resourceId),
            $exception instanceof BasketSessionNotFoundException => $this->handleNotFound($exception, $operation, $resourceId),
            $exception instanceof InvalidBasketException => $this->handleInvalidBasket($exception, $operation, $resourceId),
            $exception instanceof InvalidOrderEventException => $this->handleInvalidBasket($exception, $operation, $resourceId),
            $exception instanceof IllegalTransitionException => $this->handleDomainException($exception, $operation, $resourceId),
            $exception instanceof DomainException => $this->handleDomainException($exception, $operation, $resourceId),
            default => $this->handleGenericException($exception, $operation, $resourceId),
        };
    }

    private function handleNotFound(Throwable $exception, string $operation, ?string $resourceId): JsonResponse
    {
        $this->exceptionLogger->warning('Resource not found while ' . $operation, $exception, [
            'operation' => $operation,
            'resource_id' => $resourceId ?? 'unknown',
        ]);

        return new JsonResponse(
            $this->responseBuilder->buildNotFoundResponse(
                message: $exception->getMessage(),
                basketId: $resourceId ?? 'unknown',
                trace: $exception->getTraceAsString()
            ),
            Response::HTTP_NOT_FOUND
        );
    }

    private function handleInvalidBasket(Throwable $exception, string $operation, ?string $resourceId): JsonResponse
    {
        $this->exceptionLogger->error('Invalid basket/event while ' . $operation, $exception, [
            'operation' => $operation,
            'resource_id' => $resourceId ?? 'unknown',
        ]);

        return new JsonResponse(
            $this->responseBuilder->buildUnprocessableEntityResponse(
                $exception->getMessage(),
                $resourceId ?? 'unknown',
                $exception->getTraceAsString()
            ),
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    private function handleDomainException(Throwable $exception, string $operation, ?string $resourceId): JsonResponse
    {
        $this->exceptionLogger->error('Domain exception while ' . $operation, $exception, [
            'operation' => $operation,
            'resource_id' => $resourceId ?? 'unknown',
        ]);

        return new JsonResponse(
            $this->responseBuilder->buildUnprocessableEntityResponse(
                $exception->getMessage(),
                $resourceId ?? 'unknown',
                $exception->getTraceAsString()
            ),
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    private function handleGenericException(Throwable $exception, string $operation, ?string $resourceId): JsonResponse
    {
        $this->exceptionLogger->error('Unexpected error while ' . $operation, $exception, [
            'operation' => $operation,
            'resource_id' => $resourceId ?? 'unknown',
        ]);

        return new JsonResponse(
            $this->responseBuilder->buildInternalErrorResponse(
                sprintf('An unexpected error occurred while %s', $operation)
            ),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }
}
