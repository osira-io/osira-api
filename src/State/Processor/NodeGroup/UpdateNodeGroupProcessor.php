<?php

declare(strict_types=1);

namespace App\State\Processor\NodeGroup;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\NodeGroup\NodeGroupOutput;
use App\Dto\NodeGroup\UpdateNodeGroupInput;
use App\Service\NodeGroup\NodeGroupManager;
use App\Service\NodeGroup\NodeGroupOutputFactory;

/** @implements ProcessorInterface<UpdateNodeGroupInput, NodeGroupOutput> */
final readonly class UpdateNodeGroupProcessor implements ProcessorInterface
{
    public function __construct(private NodeGroupManager $manager, private NodeGroupOutputFactory $outputFactory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NodeGroupOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->outputFactory->create($this->manager->update(\is_string($id) ? $id : '', $data));
    }
}
