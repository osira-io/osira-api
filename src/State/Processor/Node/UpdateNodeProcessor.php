<?php

declare(strict_types=1);

namespace App\State\Processor\Node;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Node\NodeOutput;
use App\Dto\Node\UpdateNodeInput;
use App\Service\Node\NodeOutputFactory;
use App\Service\Node\NodeUpdater;

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
