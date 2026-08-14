<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Api\Resource;

final readonly class PaginationMetadata
{
    public function __construct(
        public int $currentPage,
        public int $itemsPerPage,
        public int $totalItems,
        public int $totalPages,
        public bool $hasPreviousPage,
        public bool $hasNextPage,
    ) {
    }
}
