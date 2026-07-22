<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Subscriber;

use Crehler\InpostPay\Application\Service\IncomingRsaSignatureValidator;
use Crehler\InpostPay\Domain\Exception\InvalidIncomingSignatureException;
use Crehler\InpostPay\Infrastructure\Logger\ExtendedLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\{JsonResponse, Response};
use Symfony\Component\HttpKernel\Event\{ControllerEvent, ExceptionEvent};
use Symfony\Component\HttpKernel\KernelEvents;

use function preg_match;

final readonly class IncomingSignatureSubscriber implements EventSubscriberInterface
{
    private const PROTECTED_PATH_PATTERN = '#^(?:/api/inpost)?/v1/izi/(?:basket/|order)#';

    private const REQUIRED_HEADERS = [
        'x-signature',
        'x-signature-timestamp',
        'x-public-key-ver',
        'x-public-key-hash',
    ];

    public function __construct(
        private IncomingRsaSignatureValidator $validator,
        private ExtendedLogger $extendedLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 100],
            KernelEvents::EXCEPTION => ['onKernelException', 100],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (preg_match(self::PROTECTED_PATH_PATTERN, $path) !== 1) {
            return;
        }

        $headers = [];
        foreach (self::REQUIRED_HEADERS as $header) {
            $value = $request->headers->get($header);
            if ($value === null || $value === '') {
                throw InvalidIncomingSignatureException::missingHeader($header);
            }
            $headers[$header] = $value;
        }

        $this->validator->validate(
            signature: $headers['x-signature'],
            timestamp: $headers['x-signature-timestamp'],
            keyVersion: $headers['x-public-key-ver'],
            keyHash: $headers['x-public-key-hash'],
            requestBody: $request->getContent(),
        );

        $this->extendedLogger->debug('Incoming RSA signature verified', [
            'path' => $path,
            'key_version' => $headers['x-public-key-ver'],
        ]);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof InvalidIncomingSignatureException) {
            return;
        }

        $event->setResponse(new JsonResponse(
            [
                'error_code' => 'INVALID_SIGNATURE',
                'error_message' => $throwable->getMessage(),
            ],
            Response::HTTP_UNAUTHORIZED,
        ));
    }
}
