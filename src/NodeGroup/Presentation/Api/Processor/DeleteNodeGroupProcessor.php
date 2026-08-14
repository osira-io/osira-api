<?php

declare(strict_types=1);

namespace App\NodeGroup\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\NodeGroup\Application\Service\NodeGroupManager;

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
