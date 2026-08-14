<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\NodeGroupOutput;
use App\Application\NodeGroup\NodeGroupManager;
use App\Dto\CreateNodeGroupInput;

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
