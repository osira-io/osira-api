<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PermissionCollectionOutput;
use App\Entity\Permission;
use App\Repository\PermissionRepository;
use Knp\Component\Pager\PaginatorInterface;

/** @implements ProviderInterface<PermissionCollectionOutput> */
final readonly class PermissionCollectionProvider implements ProviderInterface
{
    public function __construct(private PermissionRepository $repository, private PaginatorInterface $paginator, private PermissionOutputFactory $outputFactory, private PaginationMetadataFactory $metadataFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PermissionCollectionOutput
    {
        $parameters = PaginationParameters::fromOperation($operation);
        $pagination = $this->paginator->paginate($this->repository->createOrderedQueryBuilder(), $parameters->page, $parameters->itemsPerPage);
        $items = [];
        foreach ($pagination->getItems() as $permission) {
            if (!$permission instanceof Permission) {
                throw new \LogicException('Unexpected permission pagination result.');
            }
            $items[] = $this->outputFactory->create($permission);
        }

        return new PermissionCollectionOutput($items, $this->metadataFactory->create($pagination));
    }
}
