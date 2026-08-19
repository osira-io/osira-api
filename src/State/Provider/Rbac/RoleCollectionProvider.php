<?php

declare(strict_types=1);

namespace App\State\Provider\Rbac;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Rbac\RoleCollectionOutput;
use App\Entity\Rbac\Role;
use App\Repository\Rbac\RoleRepository;
use App\Service\Rbac\RoleOutputFactory;
use App\Service\Shared\PaginationMetadataFactory;
use App\Service\Shared\PaginationParameters;
use Knp\Component\Pager\PaginatorInterface;

/** @implements ProviderInterface<RoleCollectionOutput> */
final readonly class RoleCollectionProvider implements ProviderInterface
{
    public function __construct(private RoleRepository $repository, private PaginatorInterface $paginator, private RoleOutputFactory $outputFactory, private PaginationMetadataFactory $metadataFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): RoleCollectionOutput
    {
        $parameters = PaginationParameters::fromOperation($operation);
        $pagination = $this->paginator->paginate($this->repository->createOrderedQueryBuilder(), $parameters->page, $parameters->itemsPerPage);
        $items = [];
        foreach ($pagination->getItems() as $role) {
            if (!$role instanceof Role) {
                throw new \LogicException('Unexpected role pagination result.');
            }
            $items[] = $this->outputFactory->create($role);
        }

        return new RoleCollectionOutput($items, $this->metadataFactory->create($pagination));
    }
}
