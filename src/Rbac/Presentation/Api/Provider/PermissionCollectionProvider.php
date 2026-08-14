<?php

declare(strict_types=1);

namespace App\Rbac\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Rbac\Domain\Entity\Permission;
use App\Rbac\Infrastructure\Repository\PermissionRepository;
use App\Rbac\Presentation\Api\Factory\PermissionOutputFactory;
use App\Rbac\Presentation\Api\Resource\PermissionCollectionOutput;
use App\Shared\Application\Pagination\PaginationMetadataFactory;
use App\Shared\Application\Pagination\PaginationParameters;
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
