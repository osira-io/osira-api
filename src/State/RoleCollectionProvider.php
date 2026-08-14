<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\RoleCollectionOutput;
use App\Entity\Role;
use App\Repository\RoleRepository;
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
