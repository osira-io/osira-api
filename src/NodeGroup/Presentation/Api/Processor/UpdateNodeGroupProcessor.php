<?php

declare(strict_types=1);

namespace App\NodeGroup\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\NodeGroup\Application\Service\NodeGroupManager;
use App\NodeGroup\Presentation\Api\Dto\UpdateNodeGroupInput;
use App\NodeGroup\Presentation\Api\Factory\NodeGroupOutputFactory;
use App\NodeGroup\Presentation\Api\Resource\NodeGroupOutput;

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
