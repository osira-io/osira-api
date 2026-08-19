<?php

declare(strict_types=1);

namespace App\State\Processor\Monitoring;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Monitoring\ItemDefinitionOutput;
use App\Dto\Monitoring\UpdateItemDefinitionInput;
use App\Service\Monitoring\ItemDefinitionManager;
use App\Service\Monitoring\ItemDefinitionOutputFactory;

/** @implements ProcessorInterface<UpdateItemDefinitionInput, ItemDefinitionOutput> */
final readonly class UpdateItemDefinitionProcessor implements ProcessorInterface
{
    public function __construct(
        private ItemDefinitionManager $manager,
        private ItemDefinitionOutputFactory $outputFactory,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ItemDefinitionOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->outputFactory->create($this->manager->update(\is_string($id) ? $id : '', $data));
    }
}
