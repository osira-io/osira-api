<?php

declare(strict_types=1);

namespace App\Node\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Node\Domain\Entity\Node;
use App\Node\Infrastructure\Repository\NodeRepository;
use App\Node\Presentation\Api\Factory\NodeOutputFactory;
use App\Node\Presentation\Api\Resource\NodeCollectionOutput;
use App\Shared\Application\Pagination\PaginationMetadataFactory;
use App\Shared\Application\Pagination\PaginationParameters;
use Knp\Component\Pager\PaginatorInterface;

/** @implements ProviderInterface<NodeCollectionOutput> */
final readonly class NodeCollectionProvider implements ProviderInterface
{
    public function __construct(
        private NodeRepository $repository,
        private PaginatorInterface $paginator,
        private NodeOutputFactory $outputFactory,
        private PaginationMetadataFactory $metadataFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NodeCollectionOutput
    {
        $parameters = PaginationParameters::fromOperation($operation);
        $pagination = $this->paginator->paginate(
            $this->repository->createOrderedQueryBuilder(),
            $parameters->page,
            $parameters->itemsPerPage,
        );

        $items = [];
        foreach ($pagination->getItems() as $node) {
            if (!$node instanceof Node) {
                throw new \LogicException('Unexpected node pagination result.');
            }
            $items[] = $this->outputFactory->create($node);
        }

        return new NodeCollectionOutput($items, $this->metadataFactory->create($pagination));
    }
}
