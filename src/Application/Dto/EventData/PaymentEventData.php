<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto\EventData;

use Crehler\InpostPay\Domain\ValueObject\{PaymentStatus, PaymentType};
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

use function sprintf;

final readonly class PaymentEventData
{
    public function __construct(
        #[Assert\NotNull(message: 'Payment status is required')]
        public PaymentStatus $paymentStatus,

        #[Assert\NotBlank(message: 'Payment ID is required')]
        #[Assert\Type(type: 'string', message: 'Payment ID must be a string')]
        public string $paymentId,

        #[Assert\NotBlank(message: 'Payment reference is required')]
        #[Assert\Type(type: 'string', message: 'Payment reference must be a string')]
        public string $paymentReference,

        #[Assert\NotNull(message: 'Payment type is required')]
        public PaymentType $paymentType,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $paymentStatus = PaymentStatus::tryFrom($data['payment_status'] ?? '');
        if (!$paymentStatus) {
            throw new InvalidArgumentException(sprintf('Invalid payment status: %s', $data['payment_status'] ?? 'empty'));
        }

        $paymentType = PaymentType::getEnum($data['payment_type'] ?? '');

        return new self(
            paymentStatus: $paymentStatus,
            paymentId: (string) ($data['payment_id'] ?? ''),
            paymentReference: (string) ($data['payment_reference'] ?? ''),
            paymentType: $paymentType,
        );
    }

    public function toArray(): array
    {
        return [
            'payment_status' => $this->paymentStatus->value,
            'payment_id' => $this->paymentId,
            'payment_reference' => $this->paymentReference,
            'payment_type' => $this->paymentType->value,
        ];
    }
}
