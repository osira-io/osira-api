<?php

declare(strict_types=1);

namespace App\NodeGroup\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\NodeGroup\Domain\Entity\NodeGroup;
use App\NodeGroup\Infrastructure\Repository\NodeGroupRepository;
use App\NodeGroup\Presentation\Api\Factory\NodeGroupOutputFactory;
use App\NodeGroup\Presentation\Api\Resource\NodeGroupCollectionOutput;
use App\Shared\Application\Pagination\PaginationMetadataFactory;
use App\Shared\Application\Pagination\PaginationParameters;
use Knp\Component\Pager\PaginatorInterface;

/** @implements ProviderInterface<NodeGroupCollectionOutput> */
final readonly class NodeGroupCollectionProvider implements ProviderInterface
{
    public function __construct(
        private NodeGroupRepository $repository,
        private PaginatorInterface $paginator,
        private NodeGroupOutputFactory $outputFactory,
        private PaginationMetadataFactory $metadataFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NodeGroupCollectionOutput
    {
        $parameters = PaginationParameters::fromOperation($operation);
        $pagination = $this->paginator->paginate(
            $this->repository->createOrderedQueryBuilder(),
            $parameters->page,
            $parameters->itemsPerPage,
        );

        $items = [];
        foreach ($pagination->getItems() as $group) {
            if (!$group instanceof NodeGroup) {
                throw new \LogicException('Unexpected node group pagination result.');
            }
            $items[] = $this->outputFactory->create($group);
        }

        return new NodeGroupCollectionOutput($items, $this->metadataFactory->create($pagination));
    }
}
