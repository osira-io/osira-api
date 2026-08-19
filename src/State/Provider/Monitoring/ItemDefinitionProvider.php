<?php

declare(strict_types=1);

namespace App\State\Provider\Monitoring;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Monitoring\ItemDefinitionOutput;
use App\Entity\Monitoring\ItemDefinition;
use App\Repository\Monitoring\ItemDefinitionRepository;
use App\Service\Monitoring\ItemDefinitionOutputFactory;
use Symfony\Component\Uid\Ulid;

/** @implements ProviderInterface<ItemDefinitionOutput> */
final readonly class ItemDefinitionProvider implements ProviderInterface
{
    public function __construct(
        private ItemDefinitionRepository $repository,
        private ItemDefinitionOutputFactory $outputFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ItemDefinitionOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id) || !Ulid::isValid($id)) {
            return null;
        }
        $itemDefinition = $this->repository->find(new Ulid($id));

        return $itemDefinition instanceof ItemDefinition ? $this->outputFactory->create($itemDefinition) : null;
    }
}
