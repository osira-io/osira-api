<?php

declare(strict_types=1);

namespace App\Rbac\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Rbac\Domain\Entity\Role;
use App\Rbac\Infrastructure\Repository\RoleRepository;
use App\Rbac\Presentation\Api\Factory\RoleOutputFactory;
use App\Rbac\Presentation\Api\Resource\RoleCollectionOutput;
use App\Shared\Application\Pagination\PaginationMetadataFactory;
use App\Shared\Application\Pagination\PaginationParameters;
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
