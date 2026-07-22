<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto;

use Crehler\InpostPay\Domain\Exception\InvalidBasketException;
use Crehler\InpostPay\Domain\ValueObject\{BasketConfirmationStatus, BrowserInfo, PhoneNumber};
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use ValueError;

use function sprintf;

readonly class BasketConfirmationDto
{
    public function __construct(
        #[Assert\NotNull(message: 'Confirmation status is required')]
        public BasketConfirmationStatus $status,

        #[Assert\NotBlank(message: 'InPost basket ID is required')]
        #[Assert\Type(type: 'string', message: 'InPost basket ID must be a string')]
        public string $inpostBasketId,

        #[Assert\NotNull(message: 'Phone number is required')]
        public PhoneNumber $phoneNumber,

        #[Assert\NotNull(message: 'Browser info is required')]
        public BrowserInfo $browserInfo,

        #[Assert\Type(type: 'string', message: 'Masked phone number must be a string')]
        public ?string $maskedPhoneNumber = null,

        #[Assert\Type(type: 'string', message: 'Name must be a string')]
        public ?string $name = null,

        #[Assert\Type(type: 'string', message: 'Surname must be a string')]
        public ?string $surname = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            $status = BasketConfirmationStatus::from($data['status'] ?? '');
            $phoneNumber = new PhoneNumber(
                $data['phone_number']['country_prefix'] ?? '',
                $data['phone_number']['phone'] ?? ''
            );
            $browserInfo = new BrowserInfo(
                browserTrusted: $data['browser']['browser_trusted'] ?? false,
                browserId: $data['browser']['browser_id'] ?? null,
            );
        } catch (ValueError|InvalidArgumentException $e) {
            throw new InvalidBasketException(sprintf('Invalid basket confirmation data: %s', $e->getMessage()));
        }

        return new self(
            status: $status,
            inpostBasketId: $data['inpost_basket_id'] ?? '',
            phoneNumber: $phoneNumber,
            browserInfo: $browserInfo,
            maskedPhoneNumber: $data['masked_phone_number'] ?? null,
            name: $data['name'] ?? null,
            surname: $data['surname'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'inpost_basket_id' => $this->inpostBasketId,
            'phone_number' => $this->phoneNumber->toArray(),
            'browser' => $this->browserInfo->toArray(),
            'masked_phone_number' => $this->maskedPhoneNumber,
            'name' => $this->name,
            'surname' => $this->surname,
        ];
    }
}
