<?php

declare(strict_types=1);

namespace App\State\Processor\NodeGroup;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\NodeGroup\CreateNodeGroupInput;
use App\Dto\NodeGroup\NodeGroupOutput;
use App\Service\NodeGroup\NodeGroupManager;
use App\Service\NodeGroup\NodeGroupOutputFactory;

/** @implements ProcessorInterface<CreateNodeGroupInput, NodeGroupOutput> */
final readonly class CreateNodeGroupProcessor implements ProcessorInterface
{
    public function __construct(private NodeGroupManager $manager, private NodeGroupOutputFactory $outputFactory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NodeGroupOutput
    {
        return $this->outputFactory->create($this->manager->create($data));
    }
}
