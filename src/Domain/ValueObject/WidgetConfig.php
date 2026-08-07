<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

use Crehler\InpostPay\Domain\Enum\BindingPlace;
use InvalidArgumentException;

use function filter_var;
use function in_array;
use function sprintf;

final readonly class WidgetConfig
{
    /**
     * @var string
     */
    public const SANDBOX = 'sandbox';
    /**
     * @var string
     */
    public const PRODUCTION = 'production';

    public function __construct(
        public string $scriptUrl,
        public string $mode,
        public ?string $clientId = null,
        public ?string $clientSecret = null,
        public ?string $postId = null,
        public ?string $merchantClientId = null,
        public ?string $basketBindingApiKey = null,
        public ?string $merchantSecret = null,
        public array $displayConfigurations = [],
        public string $jsLogLevel = 'error',
    ) {
        if (empty($scriptUrl) || !filter_var($scriptUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(sprintf('Invalid script URL: %s', $scriptUrl));
        }

        if (!in_array($mode, [self::SANDBOX, self::PRODUCTION], true)) {
            throw new InvalidArgumentException(sprintf('Invalid mode: %s', $mode));
        }

        if (!in_array($jsLogLevel, ['error', 'warning', 'info', 'debug'], true)) {
            throw new InvalidArgumentException(sprintf('Invalid jsLogLevel: %s', $jsLogLevel));
        }
    }

    public function getDisplayConfig(BindingPlace|string $bindingPlace): WidgetDisplayConfig
    {
        $key = $bindingPlace instanceof BindingPlace ? $bindingPlace->value : $bindingPlace;

        if (isset($this->displayConfigurations[$key])) {
            return $this->displayConfigurations[$key];
        }

        // Return default config if not found
        $place = $bindingPlace instanceof BindingPlace
            ? $bindingPlace
            : BindingPlace::tryFrom($key) ?? BindingPlace::ProductCard;

        return new WidgetDisplayConfig(bindingPlace: $place);
    }

    public function getDisplayConfigurations(): array
    {
        return $this->displayConfigurations;
    }
}
