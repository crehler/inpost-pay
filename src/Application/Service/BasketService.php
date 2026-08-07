<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Application\Dto\{BasketConfirmationDto, BasketEventDto};
use Crehler\InpostPay\Application\Emitter\RelatedProductsEmitter;
use Crehler\InpostPay\Application\Facade\InpostPayFacadeInterface;
use Crehler\InpostPay\Domain\Aggregate\InpostBasket;
use Crehler\InpostPay\Domain\Exception\{BasketSessionNotFoundException, InvalidBasketException, InvalidPhoneNumberException, UnsupportedEventTypeException};
use Crehler\InpostPay\Domain\ValueObject\{BasketConfirmationStatus, BrowserInfo, PhoneNumber};
use Crehler\InpostPay\Infrastructure\Logger\ExceptionLogger;
use DateTimeImmutable;
use DomainException;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

use function sprintf;

readonly class BasketService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private CartOperationService $cartOperationService,
        private CartDataExtractor $cartDataExtractor,
        private InpostBasketSessionService $sessionService,
        private RelatedProductsEmitter $relatedProductsEmitter,
        private ExceptionLogger $exceptionLogger,
        private ConsentService $consentService,
        private LoggerInterface $logger,
    ) {
    }

    public function loadBasketData(string $basketId): InpostBasket
    {
        $this->logger->debug('Loading basket data', ['basket_id' => $basketId]);

        try {
            $cart = $this->cartOperationService->loadCartByBasketId($basketId);
        } catch (BasketSessionNotFoundException $e) {
            throw $e;
        } catch (InvalidBasketException $e) {
            throw new InvalidBasketException(sprintf('Cannot load basket %s: %s', $basketId, $e->getMessage()));
        }

        $session = $this->sessionService->getSessionByBasketId($basketId);
        if ($session === null) {
            $this->logger->warning('Basket session not found - basket may have been unbound or never bound', [
                'basketId' => $basketId,
                'method' => 'loadBasketData',
            ]);
            throw BasketSessionNotFoundException::forBasketId($basketId);
        }

        $context = $this->cartOperationService->createSalesChannelContextForSession($session);

        $analytics = $session->getBasketAnalytics();
        $additionalParameters = $analytics->hasClientId() ? $analytics->toAssocArray() : null;

        $summary = $this->cartDataExtractor->extractBasketSummary($cart, $context, null, $additionalParameters);
        $products = $this->cartDataExtractor->extractProducts($cart, $context);
        $deliveryOptions = $this->cartDataExtractor->extractDeliveryOptions($cart, $context, $products);
        $relatedProducts = $this->collectRelatedProducts($cart, $context);
        $consents = $this->consentService->getConsentsArrayForBasket($context);
        $promoCodes = $this->cartDataExtractor->extractPromoCodes($cart);

        return new InpostBasket(
            basketId: $basketId,
            inpostBasketId: '',
            status: BasketConfirmationStatus::SUCCESS,
            phoneNumber: new PhoneNumber(countryPrefix: '+48', phone: '000000000'),
            browserInfo: new BrowserInfo(browserTrusted: false),
            summary: $summary,
            deliveryOptions: $deliveryOptions,
            products: $products,
            relatedProducts: $relatedProducts,
            maskedPhoneNumber: null,
            name: null,
            surname: null,
            consents: $consents,
            promoCodes: $promoCodes,
        );
    }

    public function handleConfirmation(
        string $basketId,
        BasketConfirmationDto $dto,
    ): InpostBasket {
        try {
            $basket = $this->createBasketFromDto($basketId, $dto);

            $basket = $this->processConfirmation($basket);

            $this->publishDomainEvent($basket);

            return $basket;
        } catch (InvalidBasketException|DomainException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new InvalidBasketException(sprintf('Failed to process basket %s: %s', $basketId, $e->getMessage()));
        }
    }

    public function handleBasketEvent(
        string $basketId,
        BasketEventDto $dto,
    ): InpostBasket {
        try {
            try {
                $cart = $this->cartOperationService->loadCartByBasketId($basketId, [InpostPayFacadeInterface::INPOST_PAY_UPDATE_STATE]);
            } catch (BasketSessionNotFoundException $e) {
                throw $e;
            } catch (InvalidBasketException $e) {
                throw new InvalidBasketException(sprintf('Cannot load basket %s: %s', $basketId, $e->getMessage()));
            }

            $session = $this->sessionService->getSessionByBasketId($basketId);
            if ($session === null) {
                $this->logger->warning('Basket session not found during event handling', [
                    'basketId' => $basketId,
                    'eventType' => $dto->eventType->value,
                    'eventId' => $dto->eventId,
                    'method' => 'handleBasketEvent',
                ]);
                throw BasketSessionNotFoundException::forBasketId($basketId);
            }

            $confirmationResponse = $session->getConfirmationResponse();
            if ($confirmationResponse !== null && isset($confirmationResponse['phone_number'])) {
                $sessionPhoneData = $confirmationResponse['phone_number'];
                $sessionPhoneNumber = new PhoneNumber(
                    $sessionPhoneData['country_prefix'] ?? '',
                    $sessionPhoneData['phone'] ?? ''
                );

                $dtoPhoneNumber = $dto->phoneNumber->getFullNumber();
                $sessionPhone = $sessionPhoneNumber->getFullNumber();

                if ($dtoPhoneNumber !== $sessionPhone) {
                    throw InvalidPhoneNumberException::mismatchWithSession($basketId);
                }
            }

            $context = $this->cartOperationService->createSalesChannelContextForSession($session);

            $context->addState(InpostPayFacadeInterface::INPOST_PAY_UPDATE_STATE);

            $promoErrorMessage = null;

            if ($dto->eventType->isQuantityChange() && !empty($dto->eventData)) {
                $cart = $this->cartOperationService->updateProductQuantities(
                    $cart,
                    $context,
                    $dto->eventData
                );
            } elseif ($dto->eventType->isPromoCode()) {
                // Empty eventData means "remove all promo codes"
                if (empty($dto->eventData)) {
                    $cart = $this->cartOperationService->removeAllPromoCodes($cart, $context);
                } else {
                    $result = $this->cartOperationService->applyPromoCodes(
                        $cart,
                        $context,
                        $dto->eventData
                    );
                    $cart = $result->cart;
                    $promoErrorMessage = $result->errorMessage;
                }
            } elseif ($dto->eventType->isRelatedProduct() && !empty($dto->eventData)) {
                $cart = $this->cartOperationService->addRelatedProducts(
                    $cart,
                    $context,
                    $dto->eventData
                );
            }

            $analytics = $session->getBasketAnalytics();
            $additionalParameters = $analytics->hasClientId() ? $analytics->toAssocArray() : null;

            $summary = $this->cartDataExtractor->extractBasketSummary($cart, $context, $promoErrorMessage, $additionalParameters);
            $products = $this->cartDataExtractor->extractProducts($cart, $context);
            $deliveryOptions = $this->cartDataExtractor->extractDeliveryOptions($cart, $context, $products);

            try {
                $this->sessionService->updateSession($basketId, [
                    'lastEventId' => $dto->eventId,
                    'lastEventType' => $dto->eventType->value,
                    'lastEventTime' => $dto->eventDateTime->format('c'),
                ]);
            } catch (Exception $e) {
                $this->exceptionLogger->warning('Failed to update session after event (non-critical)', $e, [
                    'basketId' => $basketId,
                    'eventId' => $dto->eventId,
                    'eventType' => $dto->eventType->value,
                ]);
            }

            $relatedProducts = $this->collectRelatedProducts($cart, $context);
            $consents = $this->consentService->getConsentsArrayForBasket($context);
            $promoCodes = $this->cartDataExtractor->extractPromoCodes($cart);

            return new InpostBasket(
                basketId: $basketId,
                inpostBasketId: $session->getInpostBasketId() ?? '',
                status: BasketConfirmationStatus::SUCCESS,
                phoneNumber: $dto->phoneNumber,
                browserInfo: new BrowserInfo(browserTrusted: false),
                summary: $summary,
                deliveryOptions: $deliveryOptions,
                products: $products,
                relatedProducts: $relatedProducts,
                consents: $consents,
                promoCodes: $promoCodes,
            );
        } catch (InvalidBasketException|InvalidPhoneNumberException|UnsupportedEventTypeException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new InvalidBasketException(sprintf('Failed to process basket event %s: %s', $basketId, $e->getMessage()));
        }
    }

    private function createBasketFromDto(
        string $basketId,
        BasketConfirmationDto $dto,
    ): InpostBasket {
        try {
            $cart = $this->cartOperationService->loadCartByBasketId($basketId);
        } catch (BasketSessionNotFoundException $e) {
            throw $e;
        } catch (InvalidBasketException $e) {
            throw new InvalidBasketException(sprintf('Cannot load basket %s: %s', $basketId, $e->getMessage()));
        }

        $session = $this->sessionService->getSessionByBasketId($basketId);
        if ($session === null) {
            $this->logger->warning('Basket session not found during confirmation', [
                'basketId' => $basketId,
                'inpostBasketId' => $dto->inpostBasketId,
                'method' => 'createBasketFromDto',
            ]);
            throw BasketSessionNotFoundException::forBasketId($basketId);
        }

        $context = $this->cartOperationService->createSalesChannelContextForSession($session);

        $analytics = $session->getBasketAnalytics();
        $additionalParameters = $analytics->hasClientId() ? $analytics->toAssocArray() : null;

        $summary = $this->cartDataExtractor->extractBasketSummary($cart, $context, null, $additionalParameters);
        $products = $this->cartDataExtractor->extractProducts($cart, $context);
        $deliveryOptions = $this->cartDataExtractor->extractDeliveryOptions($cart, $context, $products);

        try {
            $this->sessionService->updateSession($basketId, [
                'inpostBasketId' => $dto->inpostBasketId,
                'confirmationResponse' => $dto->toArray(),
            ]);
        } catch (Exception $e) {
            $this->exceptionLogger->warning('Failed to update session after confirmation (non-critical)', $e, [
                'basketId' => $basketId,
                'inpostBasketId' => $dto->inpostBasketId,
            ]);
        }

        $relatedProducts = $this->collectRelatedProducts($cart, $context);
        $consents = $this->consentService->getConsentsArrayForBasket($context);
        $promoCodes = $this->cartDataExtractor->extractPromoCodes($cart);

        return new InpostBasket(
            basketId: $basketId,
            inpostBasketId: $dto->inpostBasketId,
            status: $dto->status,
            phoneNumber: $dto->phoneNumber,
            browserInfo: $dto->browserInfo,
            summary: $summary,
            deliveryOptions: $deliveryOptions,
            products: $products,
            relatedProducts: $relatedProducts,
            maskedPhoneNumber: $dto->maskedPhoneNumber,
            name: $dto->name,
            surname: $dto->surname,
            consents: $consents,
            promoCodes: $promoCodes,
        );
    }

    private function processConfirmation(InpostBasket $basket): InpostBasket
    {
        $now = new DateTimeImmutable();

        if ($basket->getStatus()->isSuccess()) {
            return $basket->confirm($now);
        }

        if ($basket->getStatus()->isRejected()) {
            return $basket->reject($now);
        }

        throw new DomainException(sprintf('Cannot process basket with status: %s', $basket->getStatus()->value));
    }

    private function publishDomainEvent(InpostBasket $basket): void
    {
        $event = $basket->getDomainEvent();

        if ($event === null) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }

    private function collectRelatedProducts(
        Cart $cart,
        SalesChannelContext $context,
    ): array {
        return $this->relatedProductsEmitter->emit($cart, $context);
    }
}
