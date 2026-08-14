<?php

declare(strict_types=1);

namespace App\State\Processor\NodeGroup;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Service\NodeGroup\NodeGroupManager;

/** @implements ProcessorInterface<object, void> */
final readonly class DeleteNodeGroupProcessor implements ProcessorInterface
{
    public function __construct(private NodeGroupManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $id = $uriVariables['id'] ?? '';
        $this->manager->delete(\is_string($id) ? $id : '');
    }
}
