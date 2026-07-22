<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\StoreApi;

use Crehler\InpostPay\Application\Facade\InpostPayFacadeInterface;
use Crehler\InpostPay\Application\Service\InpostBasketSessionService;
use Crehler\InpostPay\Domain\Cart\Error\EmptyCartError;
use Crehler\InpostPay\Domain\Exception\{EmptyCartException, InpostPayEndpointException};
use Crehler\InpostPay\Infrastructure\Logger\{ExceptionLogger, ExtendedLogger};
use Crehler\InpostPay\Infrastructure\StoreApi\Abstract\AbstractBindBasketRoute;
use Exception;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;

use function is_array;
use function json_decode;
use function str_contains;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class BindBasketRoute extends AbstractBindBasketRoute
{
    public function __construct(
        private readonly InpostPayFacadeInterface $inpostPayFacade,
        private readonly InpostBasketSessionService $basketSessionService,
        private readonly LoggerInterface $logger,
        private readonly ExceptionLogger $exceptionLogger,
        private readonly ExtendedLogger $extendedLogger,
    ) {
    }

    public function getDecorated(): AbstractBindBasketRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/inpost/basket/bind',
        name: 'store-api.inpost.basket.bind',
        defaults: ['_loginRequired' => false],
        methods: ['POST']
    )]
    public function bindBasket(Request $request, SalesChannelContext $context): JsonResponse
    {
        $payload = $this->readPayload($request);
        $productId = $payload['productId'] ?? null;
        $quantity = (int) ($payload['quantity'] ?? 1);

        $this->extendedLogger->debugForBasket($context->getToken(), 'StoreApi: bindBasket', [
            'has_product_id' => (bool) $productId,
        ]);

        try {
            if ($productId) {
                return $this->bindBasketWithProduct((string) $productId, $quantity, $context, $request);
            }

            return $this->bindExistingBasket($context, $request);
        } catch (Exception $e) {
            $this->exceptionLogger->error('Unexpected error during basket binding', $e, [
                'basketId' => $context->getToken(),
                'productId' => $productId,
                'quantity' => $quantity,
            ]);

            return new JsonResponse([
                'error' => 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(
        path: '/store-api/inpost/basket/status',
        name: 'store-api.inpost.basket.status',
        defaults: ['_loginRequired' => false],
        methods: ['GET']
    )]
    public function getBindingStatus(Request $request, SalesChannelContext $context): JsonResponse
    {
        $basketId = $context->getToken();

        $this->extendedLogger->debugForBasket($basketId, 'StoreApi: getBindingStatus');

        $session = $this->basketSessionService->getSessionByBasketId($basketId);

        if ($session === null) {
            return new JsonResponse([
                'bound' => false,
                'basketBindingApiKey' => null,
            ]);
        }

        return new JsonResponse([
            'bound' => true,
            'basketBindingApiKey' => $session->basketBindingApiKey,
        ]);
    }

    #[Route(
        path: '/store-api/inpost/order/confirmation-url',
        name: 'store-api.inpost.order.confirmation_url',
        defaults: ['_loginRequired' => false],
        methods: ['GET']
    )]
    public function getOrderConfirmationUrl(SalesChannelContext $context): JsonResponse
    {
        $basketId = $context->getToken();

        $this->extendedLogger->debugForBasket($basketId, 'StoreApi: getOrderConfirmationUrl');

        $session = $this->basketSessionService->getSessionByBasketId($basketId);

        if ($session === null || $session->getOrderId() === null) {
            return new JsonResponse(['url' => null], Response::HTTP_NOT_FOUND);
        }

        $finishUrl = '/checkout/finish?orderId=' . $session->getOrderId();

        return new JsonResponse(['url' => $finishUrl]);
    }

    private function readPayload(Request $request): array
    {
        if (!$request->request->all() && str_contains((string) $request->headers->get('Content-Type'), 'application/json')) {
            $content = (string) $request->getContent();
            if ($content === '') {
                return [];
            }

            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : [];
        }

        return $request->request->all();
    }

    private function bindBasketWithProduct(string $productId, int $quantity, SalesChannelContext $context, Request $request): JsonResponse
    {
        try {
            if ($quantity < 1) {
                return new JsonResponse([
                    'error' => 'Quantity must be at least 1',
                ], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->inpostPayFacade->bindBasketWithProduct(
                $productId,
                $quantity,
                $context,
                $request
            );

            $this->logger->info('Basket binding with product successful', [
                'basketId' => $result['basketId'],
                'productId' => $productId,
                'quantity' => $quantity,
            ]);

            return new JsonResponse($result);
        } catch (InpostPayEndpointException $e) {
            $this->exceptionLogger->error('InPost API error during basket binding with product', $e, [
                'productId' => $productId,
                'quantity' => $quantity,
            ]);

            return new JsonResponse([
                'error' => 'Failed to bind basket with InPost Pay',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    private function bindExistingBasket(SalesChannelContext $context, Request $request): JsonResponse
    {
        try {
            $result = $this->inpostPayFacade->bindBasket($context, $request);

            $this->logger->info('Existing basket binding successful', [
                'basketId' => $result['basketId'],
                'cartItemCount' => $result['cartItemCount'],
            ]);

            return new JsonResponse($result);
        } catch (EmptyCartException $e) {
            $this->exceptionLogger->warning('Attempted to bind empty cart', $e, [
                'basketId' => $context->getToken(),
            ]);

            $cartError = new EmptyCartError($context->getToken());

            return new JsonResponse([
                'error' => 'Cart is empty',
                'cartError' => $cartError->getId(),
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (InpostPayEndpointException $e) {
            $this->exceptionLogger->error('InPost API error during existing basket binding', $e, [
                'basketId' => $context->getToken(),
            ]);

            return new JsonResponse([
                'error' => 'Failed to bind basket with InPost Pay',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
