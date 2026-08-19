<?php

declare(strict_types=1);

namespace App\Service\Shared;

use ApiPlatform\Metadata\Operation;

final readonly class PaginationParameters
{
    public const int DEFAULT_ITEMS_PER_PAGE = 25;
    public const int MAX_ITEMS_PER_PAGE = 100;

    public function __construct(public int $page, public int $itemsPerPage)
    {
    }

    public static function fromOperation(Operation $operation): self
    {
        $parameters = $operation->getParameters();
        $page = $parameters?->get('page')?->getValue(1);
        $itemsPerPage = $parameters?->get('itemsPerPage')?->getValue(self::DEFAULT_ITEMS_PER_PAGE);

        return new self(
            \is_int($page) && $page > 0 ? $page : 1,
            \is_int($itemsPerPage) && $itemsPerPage > 0 && $itemsPerPage <= self::MAX_ITEMS_PER_PAGE
                ? $itemsPerPage
                : self::DEFAULT_ITEMS_PER_PAGE,
        );
    }
}
