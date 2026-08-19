<?php

declare(strict_types=1);

namespace App\State\Processor\Monitoring;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Monitoring\CreateItemDefinitionInput;
use App\Dto\Monitoring\ItemDefinitionOutput;
use App\Service\Monitoring\ItemDefinitionManager;
use App\Service\Monitoring\ItemDefinitionOutputFactory;

/** @implements ProcessorInterface<CreateItemDefinitionInput, ItemDefinitionOutput> */
final readonly class CreateItemDefinitionProcessor implements ProcessorInterface
{
    public function __construct(
        private ItemDefinitionManager $manager,
        private ItemDefinitionOutputFactory $outputFactory,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ItemDefinitionOutput
    {
        return $this->outputFactory->create($this->manager->create($data));
    }
}
