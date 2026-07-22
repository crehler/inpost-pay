<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Api\Builder;

use Crehler\InpostPay\Domain\Aggregate\{InpostBasket, Order};
use Crehler\InpostPay\Infrastructure\Serializer\InpostBasketSerializer;
use Symfony\Component\Validator\ConstraintViolationListInterface;

readonly class InpostApiResponseBuilder
{
    public function __construct(
        private InpostBasketSerializer $serializer,
    ) {
    }

    public function buildConfirmationResponse(InpostBasket $basket): array
    {
        return $this->serializer->toInpostApiResponse($basket);
    }

    public function buildOrderResponse(Order $order): array
    {
        return $order->toArray();
    }

    public function buildBadRequestResponse(string $message, array $violations = []): array
    {
        $response = [
            'error' => 'bad_request',
            'message' => $message,
        ];

        if (!empty($violations)) {
            $response['violations'] = $violations;
        }

        return $response;
    }

    public function buildNotFoundResponse(string $message, string $basketId, string $trace): array
    {
        return [
            'error' => 'not_found',
            'message' => $message,
            'basket_id' => $basketId,
            'trace' => $trace,
        ];
    }

    public function buildUnprocessableEntityResponse(string $message, string $basketId, string $trace): array
    {
        return [
            'error' => 'unprocessable_entity',
            'message' => $message,
            'basket_id' => $basketId,
            'trace' => $trace,
        ];
    }

    public function buildInternalErrorResponse(string $message): array
    {
        return [
            'error' => 'internal_server_error',
            'message' => $message,
        ];
    }

    public static function formatValidationViolations(ConstraintViolationListInterface $violations): array
    {
        $formatted = [];

        foreach ($violations as $violation) {
            $path = $violation->getPropertyPath() ?: 'root';
            $formatted[] = [
                'field' => $path,
                'message' => $violation->getMessage(),
            ];
        }

        return $formatted;
    }
}
