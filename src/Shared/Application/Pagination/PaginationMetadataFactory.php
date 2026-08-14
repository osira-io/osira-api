<?php

declare(strict_types=1);

namespace App\Shared\Application\Pagination;

use App\Shared\Presentation\Api\Resource\PaginationMetadata;
use Knp\Component\Pager\Pagination\PaginationInterface;

final readonly class PaginationMetadataFactory
{
    /** @param PaginationInterface<int, mixed> $pagination */
    public function create(PaginationInterface $pagination): PaginationMetadata
    {
        $currentPage = $pagination->getCurrentPageNumber();
        $itemsPerPage = $pagination->getItemNumberPerPage();
        $totalItems = $pagination->getTotalItemCount();
        $totalPages = 0 === $totalItems ? 0 : (int) ceil($totalItems / $itemsPerPage);

        return new PaginationMetadata(
            $currentPage,
            $itemsPerPage,
            $totalItems,
            $totalPages,
            $currentPage > 1,
            $currentPage < $totalPages,
        );
    }
}
