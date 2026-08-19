<?php

declare(strict_types=1);

namespace App\State\Provider\Monitoring;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Monitoring\ItemDefinitionCollectionOutput;
use App\Entity\Monitoring\ItemDefinition;
use App\Repository\Monitoring\ItemDefinitionRepository;
use App\Service\Monitoring\ItemDefinitionOutputFactory;
use App\Service\Shared\PaginationMetadataFactory;
use App\Service\Shared\PaginationParameters;
use Knp\Component\Pager\PaginatorInterface;

/** @implements ProviderInterface<ItemDefinitionCollectionOutput> */
final readonly class ItemDefinitionCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ItemDefinitionRepository $repository,
        private PaginatorInterface $paginator,
        private ItemDefinitionOutputFactory $outputFactory,
        private PaginationMetadataFactory $metadataFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ItemDefinitionCollectionOutput
    {
        $parameters = PaginationParameters::fromOperation($operation);
        $pagination = $this->paginator->paginate(
            $this->repository->createOrderedQueryBuilder(),
            $parameters->page,
            $parameters->itemsPerPage,
        );

        $items = [];
        foreach ($pagination->getItems() as $itemDefinition) {
            if (!$itemDefinition instanceof ItemDefinition) {
                throw new \LogicException('Unexpected item definition pagination result.');
            }
            $items[] = $this->outputFactory->create($itemDefinition);
        }

        return new ItemDefinitionCollectionOutput($items, $this->metadataFactory->create($pagination));
    }
}
