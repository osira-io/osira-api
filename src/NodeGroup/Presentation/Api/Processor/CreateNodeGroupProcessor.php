<?php

declare(strict_types=1);

namespace App\NodeGroup\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\NodeGroup\Application\Service\NodeGroupManager;
use App\NodeGroup\Presentation\Api\Dto\CreateNodeGroupInput;
use App\NodeGroup\Presentation\Api\Factory\NodeGroupOutputFactory;
use App\NodeGroup\Presentation\Api\Resource\NodeGroupOutput;

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
