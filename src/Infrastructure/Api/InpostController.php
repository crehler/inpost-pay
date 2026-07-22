<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Api;

use Crehler\InpostPay\Application\Dto\{BasketConfirmationDto, BasketEventDto, CreateOrderDto, OrderEventDto, RefundRequestDto, TransactionQueryDto, WebhookPayloadDto};
use Crehler\InpostPay\Application\Facade\InpostPayFacadeInterface;
use Crehler\InpostPay\Application\Service\BasketService;
use Crehler\InpostPay\Domain\Exception\{InpostPayEndpointException, InvalidWebhookSignatureException, OrderNotFoundException};
use Crehler\InpostPay\Infrastructure\Api\Builder\InpostApiResponseBuilder;
use Crehler\InpostPay\Infrastructure\Logger\ExtendedLogger;
use Crehler\InpostPay\Infrastructure\Serializer\RequestDeserializer;
use Crehler\InpostPay\Infrastructure\Service\ExceptionHandlerService;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

use function json_encode;
use function strlen;

#[Route(defaults: ['_routeScope' => ['api']])]
final class InpostController extends AbstractController
{
    public function __construct(
        private readonly BasketService $basketService,
        private readonly InpostApiResponseBuilder $responseBuilder,
        private readonly RequestDeserializer $deserializer,
        private readonly InpostPayFacadeInterface $inpostPayFacade,
        private readonly ExceptionHandlerService $exceptionHandler,
        private readonly ExtendedLogger $extendedLogger,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/api/inpost/v1/izi/basket/{basketId}/confirmation',
        name: 'api.inpost.basket.confirmation',
        defaults: ['auth_required' => false],
        methods: ['POST'],
    )]
    #[Route(
        path: '/v1/izi/basket/{basketId}/confirmation',
        name: 'frontend.inpost.basket.confirmation',
        defaults: ['_routeScope' => ['storefront'], 'auth_required' => false],
        methods: ['POST'],
    )]
    public function confirmBasket(
        string $basketId,
        Request $request,
    ): JsonResponse {
        $this->extendedLogger->debugForBasket($basketId, 'InpostController: confirmBasket');

        try {
            $data = $this->deserializer->decodeRequest($request);
            $confirmationDto = BasketConfirmationDto::fromArray($data);

            $confirmedBasket = $this->basketService->handleConfirmation(
                $basketId,
                $confirmationDto,
            );

            $responsePayload = $this->responseBuilder->buildConfirmationResponse($confirmedBasket);
            $this->logOutgoingBasketPayload($basketId, 'confirmBasket', $responsePayload);

            return new JsonResponse(
                $responsePayload,
                Response::HTTP_OK
            );
        } catch (Throwable $e) {
            return $this->exceptionHandler->handle($e, 'processing the confirmation', $basketId);
        }
    }

    #[Route(
        path: '/api/inpost/v1/izi/basket/{basketId}',
        name: 'api.inpost.basket.get',
        defaults: ['auth_required' => false],
        methods: ['GET'],
    )]
    #[Route(
        path: '/v1/izi/basket/{basketId}',
        name: 'frontend.inpost.basket.get',
        defaults: ['_routeScope' => ['storefront'], 'auth_required' => false],
        methods: ['GET'],
    )]
    public function getBasket(
        string $basketId,
    ): JsonResponse {
        $this->extendedLogger->debugForBasket($basketId, 'InpostController: getBasket');

        try {
            $basketData = $this->inpostPayFacade->getBasketData($basketId);

            $this->logOutgoingBasketPayload($basketId, 'getBasket', $basketData);

            return new JsonResponse(
                $basketData,
                Response::HTTP_OK
            );
        } catch (Throwable $e) {
            return $this->exceptionHandler->handle($e, 'retrieving the basket', $basketId);
        }
    }

    #[Route(
        path: '/api/inpost/v1/izi/basket/{basketId}/binding',
        name: 'api.inpost.basket.delete',
        defaults: ['auth_required' => false],
        methods: ['DELETE'],
    )]
    #[Route(
        path: '/v1/izi/basket/{basketId}/binding',
        name: 'frontend.inpost.basket.delete',
        defaults: ['_routeScope' => ['storefront'], 'auth_required' => false],
        methods: ['DELETE'],
    )]
    public function deleteBasket(
        string $basketId,
    ): JsonResponse {
        $this->extendedLogger->debugForBasket($basketId, 'InpostController: deleteBasket - DELETE notification from InPost, local cleanup only');

        // InPost calls this endpoint *after* it has already removed the binding
        // on its side (widget-initiated unbind or "remove basket" from the
        // mobile app). Only local cleanup here - never echo a DELETE back to
        // /v1/izi/basket/{id}/binding: InPost replies 500 on a missing binding
        // and that 500 feeds the 90% errors / 120s limiter (integration drops).
        try {
            $this->inpostPayFacade->desynchronizeBasketLocally($basketId);

            return new JsonResponse(
                ['message' => 'Basket desynchronized successfully'],
                Response::HTTP_OK
            );
        } catch (Throwable $e) {
            return $this->exceptionHandler->handle($e, 'desynchronizing the basket', $basketId);
        }
    }

    #[Route(
        path: '/api/inpost/v1/izi/basket/{basketId}/event',
        name: 'api.inpost.basket.event',
        defaults: ['auth_required' => false],
        methods: ['POST'],
    )]
    #[Route(
        path: '/v1/izi/basket/{basketId}/event',
        name: 'frontend.inpost.basket.event',
        defaults: ['_routeScope' => ['storefront'], 'auth_required' => false],
        methods: ['POST'],
    )]
    public function handleBasketEvent(
        string $basketId,
        Request $request,
    ): JsonResponse {
        $this->extendedLogger->debugForBasket($basketId, 'InpostController: handleBasketEvent');

        try {
            $data = $this->deserializer->decodeRequest($request);
            $eventDto = BasketEventDto::fromArray($data);

            $basketData = $this->inpostPayFacade->handleBasketEvent(
                $basketId,
                $eventDto,
            );

            $this->logOutgoingBasketPayload($basketId, 'handleBasketEvent', $basketData);

            return new JsonResponse(
                $basketData,
                Response::HTTP_OK
            );
        } catch (Throwable $e) {
            return $this->exceptionHandler->handle($e, 'processing the basket event', $basketId);
        }
    }

    #[Route(
        path: '/api/inpost/v2/izi/basket/{basketId}',
        name: 'api.inpost.basket.update',
        defaults: ['auth_required' => false],
        methods: ['PUT'],
    )]
    #[Route(
        path: '/v2/izi/basket/{basketId}',
        name: 'frontend.inpost.basket.update',
        defaults: ['_routeScope' => ['storefront'], 'auth_required' => false],
        methods: ['PUT'],
    )]
    public function updateBasket(
        string $basketId,
    ): JsonResponse {
        $this->extendedLogger->debugForBasket($basketId, 'InpostController: updateBasket');

        try {
            $response = $this->inpostPayFacade->updateBasket($basketId);

            $this->logOutgoingBasketPayload($basketId, 'updateBasket', $response);

            return new JsonResponse(
                $response,
                Response::HTTP_OK
            );
        } catch (Throwable $e) {
            return $this->exceptionHandler->handle($e, 'updating the basket', $basketId);
        }
    }

    #[Route(
        path: '/api/inpost/v1/izi/order',
        name: 'api.inpost.order.create',
        defaults: ['auth_required' => false],
        methods: ['POST'],
    )]
    #[Route(
        path: '/v1/izi/order',
        name: 'frontend.inpost.order.create',
        defaults: ['_routeScope' => ['storefront'], 'auth_required' => false],
        methods: ['POST'],
    )]
    public function createOrder(
        Request $request,
    ): JsonResponse {
        $this->extendedLogger->debug('InpostController: createOrder', [
            'payload_size' => strlen($request->getContent()),
            'raw_body' => $request->getContent(),
        ]);

        try {
            $data = $this->deserializer->decodeRequest($request);
            $orderDto = CreateOrderDto::fromArray($data);

            $response = $this->inpostPayFacade->createOrder($orderDto);

            return new JsonResponse(
                $response,
                Response::HTTP_CREATED
            );
        } catch (Throwable $e) {
            return $this->exceptionHandler->handle($e, 'creating the order', 'unknown');
        }
    }

    #[Route(
        path: '/api/inpost/v1/izi/order/{orderId}',
        name: 'api.inpost.order.get',
        defaults: ['auth_required' => false],
        methods: ['GET'],
    )]
    #[Route(
        path: '/v1/izi/order/{orderId}',
        name: 'frontend.inpost.order.get',
        defaults: ['_routeScope' => ['storefront'], 'auth_required' => false],
        methods: ['GET'],
    )]
    public function getOrder(
        string $orderId,
    ): JsonResponse {
        $this->extendedLogger->debug('InpostController: getOrder', ['orderId' => $orderId]);

        try {
            $response = $this->inpostPayFacade->getOrder($orderId);

            return new JsonResponse(
                $response,
                Response::HTTP_OK
            );
        } catch (OrderNotFoundException $e) {
            return new JsonResponse(
                ['error' => 'not_found', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
                Response::HTTP_NOT_FOUND
            );
        } catch (Throwable $e) {
            return $this->exceptionHandler->handle($e, 'retrieving the order', $orderId);
        }
    }

    #[Route(
        path: '/api/inpost/v1/izi/order/{orderId}/event',
        name: 'api.inpost.order.event',
        defaults: ['_routeScope' => ['api'], 'auth_required' => false],
        methods: ['POST'],
    )]
    #[Route(
        path: '/v1/izi/order/{orderId}/event',
        name: 'frontend.inpost.order.event',
        defaults: ['_routeScope' => ['storefront'], 'auth_required' => false],
        methods: ['POST'],
    )]
    public function handleOrderEvent(
        string $orderId,
        Request $request,
    ): JsonResponse {
        $this->extendedLogger->debug('InpostController: handleOrderEvent', ['orderId' => $orderId]);

        try {
            $data = $this->deserializer->decodeRequest($request);
            $eventDto = OrderEventDto::fromArray($data);

            $response = $this->inpostPayFacade->handleOrderEvent(
                $orderId,
                $eventDto,
            );

            return new JsonResponse(
                $response,
                Response::HTTP_OK
            );
        } catch (Throwable $e) {
            return $this->exceptionHandler->handle(
                $e,
                'processing the order event',
                $orderId
            );
        }
    }

    #[Route(
        path: '/api/inpost/events',
        name: 'api.inpost.webhook.events',
        defaults: ['auth_required' => false],
        methods: ['POST'],
    )]
    #[Route(
        path: '/inpost/events',
        name: 'frontend.inpost.webhook.events',
        defaults: ['_routeScope' => ['storefront'], 'auth_required' => false],
        methods: ['POST'],
    )]
    public function handleWebhook(Request $request): JsonResponse
    {
        $this->extendedLogger->debug('InpostController: handleWebhook', [
            'api_version' => $request->headers->get('X-API-Version'),
            'has_signature' => $request->headers->has('X-Signature'),
        ]);

        try {
            $apiVersion = $request->headers->get('X-API-Version');
            $signature = $request->headers->get('X-Signature');

            if (!$apiVersion || !$signature) {
                throw new InvalidArgumentException('X-API-Version and X-Signature headers are required');
            }

            $payload = $this->deserializer->decodeRequest($request);

            $webhookDto = WebhookPayloadDto::fromRequest(
                payload: $payload,
                apiVersion: $apiVersion,
                signature: $signature,
            );

            $result = $this->inpostPayFacade->processWebhook($webhookDto);

            return new JsonResponse($result->toArray(), Response::HTTP_OK);
        } catch (InvalidWebhookSignatureException $e) {
            return new JsonResponse(
                ['error' => 'invalid_signature', 'message' => $e->getMessage()],
                Response::HTTP_UNAUTHORIZED
            );
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => 'invalid_payload', 'message' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (Throwable $e) {
            return $this->exceptionHandler->handle($e, 'processing webhook', 'webhook');
        }
    }

    #[Route(
        path: '/api/v1/izi/transaction',
        name: 'api.inpost.transaction.list',
        defaults: ['auth_required' => true],
        methods: ['GET'],
    )]
    public function getTransactions(Request $request): JsonResponse
    {
        $this->extendedLogger->debug('InpostController: getTransactions', [
            'query' => $request->query->all(),
        ]);

        try {
            $queryDto = TransactionQueryDto::fromRequest($request);

            $response = $this->inpostPayFacade->getTransactions($queryDto);

            return new JsonResponse($response->toArray(), Response::HTTP_OK);
        } catch (InpostPayEndpointException $e) {
            return new JsonResponse(
                ['error' => 'api_error', 'message' => $e->getMessage()],
                Response::HTTP_BAD_GATEWAY
            );
        } catch (Throwable $e) {
            return $this->exceptionHandler->handle($e, 'fetching transactions', 'transactions');
        }
    }

    #[Route(
        path: '/api/v1/izi/transaction/{transactionId}/refund',
        name: 'api.inpost.transaction.refund',
        defaults: ['auth_required' => true],
        methods: ['POST'],
    )]
    public function requestRefund(string $transactionId, Request $request): JsonResponse
    {
        $this->extendedLogger->debug('InpostController: requestRefund', ['transactionId' => $transactionId]);

        try {
            $refundDto = RefundRequestDto::fromRequest($transactionId, $request, $this->deserializer);

            $response = $this->inpostPayFacade->requestRefund($refundDto);

            return new JsonResponse($response->toArray(), Response::HTTP_OK);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => 'invalid_request', 'message' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (InpostPayEndpointException $e) {
            return new JsonResponse(
                ['error' => 'api_error', 'message' => $e->getMessage()],
                Response::HTTP_BAD_GATEWAY
            );
        } catch (Throwable $e) {
            return $this->exceptionHandler->handle($e, 'processing refund request', $transactionId);
        }
    }

    private function logOutgoingBasketPayload(string $basketId, string $endpoint, mixed $payload): void
    {
        $this->extendedLogger->debugForBasket($basketId, 'Outgoing basket payload to InPost', [
            'endpoint' => $endpoint,
            'payload_json' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
