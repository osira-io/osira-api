<?php

declare(strict_types=1);

namespace App\Node\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Node\Application\Service\NodeUpdater;
use App\Node\Presentation\Api\Dto\UpdateNodeInput;
use App\Node\Presentation\Api\Factory\NodeOutputFactory;
use App\Node\Presentation\Api\Resource\NodeOutput;

/** @implements ProcessorInterface<UpdateNodeInput, NodeOutput> */
final readonly class UpdateNodeProcessor implements ProcessorInterface
{
    public function __construct(private NodeUpdater $updater, private NodeOutputFactory $outputFactory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NodeOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->outputFactory->create($this->updater->update(\is_string($id) ? $id : '', $data));
    }
}
