<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\NodeGroupCollectionOutput;
use App\Entity\NodeGroup;
use App\Repository\NodeGroupRepository;
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
