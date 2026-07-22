<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto;

use function array_map;

final readonly class TransactionResponseDto
{
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $count,
    ) {
    }

    public static function fromApiResponse(array $data): self
    {
        $items = array_map(
            fn (array $item) => TransactionItemDto::fromArray($item),
            $data['items'] ?? []
        );

        return new self(
            items: $items,
            page: (int) ($data['page'] ?? 0),
            perPage: (int) ($data['per_page'] ?? 20),
            count: (int) ($data['count'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'items' => array_map(fn (TransactionItemDto $item) => $item->toArray(), $this->items),
            'page' => $this->page,
            'per_page' => $this->perPage,
            'count' => $this->count,
        ];
    }
}
