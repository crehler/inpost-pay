<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Subscriber;

use Crehler\InpostPay\Infrastructure\Logger\IncomingRequestLogFormatter;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function preg_match;

/**
 * Records the requests InPost makes to this shop in the plugin log at debug level. Until now
 * each endpoint logged its own payload by hand, so whatever nobody had thought to log was
 * invisible - and questions about the InPost contract (what a PROMO_CODES event really
 * carries, for one) could not be settled without the raw request.
 *
 * Written in two parts on purpose. Metadata goes out ahead of IncomingSignatureSubscriber
 * (priority 100), so a request turned away for a bad signature still leaves a trace. The body
 * is written only behind that check, and only for a request it actually verified: anyone can
 * put an x-signature header on a request, so header presence proves nothing and would let a
 * stranger push arbitrary text into the merchant's log.
 *
 * The consequence is that bodies are logged for the RSA-protected basket and order API only.
 * The webhook verifies its own signature inside the controller, past the point this subscriber
 * runs, so it contributes metadata but no body.
 */
final readonly class IncomingRequestLoggerSubscriber implements EventSubscriberInterface
{
    /**
     * The endpoints InPost itself calls: the /izi/ API in both its storefront and admin-api
     * form, plus the webhook. Deliberately leaves out routes our own code calls - the
     * storefront's /store-api/inpost and /checkout/inpost, and the admin panel's
     * /api/v1/izi/transaction - because those are not incoming InPost traffic.
     */
    private const INPOST_PATH_PATTERN = '#^(?:/api/inpost)?/v[12]/izi/|^(?:/api)?/inpost/events$#';

    public function __construct(
        private LoggerInterface $logger,
        private IncomingRequestLogFormatter $formatter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['logRequest', 200],
                ['logVerifiedBody', 50],
            ],
        ];
    }

    public function logRequest(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isInpostRequest($request)) {
            return;
        }

        $this->logger->debug('Incoming InPost request', $this->formatter->metadata($request));
    }

    public function logVerifiedBody(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isInpostRequest($request)) {
            return;
        }

        if ($request->attributes->get(IncomingSignatureSubscriber::ATTRIBUTE_VERIFIED) !== true) {
            return;
        }

        $this->logger->debug('Incoming InPost request body', $this->formatter->body($request));
    }

    private function isInpostRequest(Request $request): bool
    {
        return preg_match(self::INPOST_PATH_PATTERN, $request->getPathInfo()) === 1;
    }
}
