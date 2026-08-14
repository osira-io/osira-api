<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\NodeGroupOutput;
use App\Entity\NodeGroup;
use App\Repository\NodeGroupRepository;
use Symfony\Component\Uid\Ulid;

/** @implements ProviderInterface<NodeGroupOutput> */
final readonly class NodeGroupProvider implements ProviderInterface
{
    public function __construct(private NodeGroupRepository $repository, private NodeGroupOutputFactory $outputFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?NodeGroupOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id) || !Ulid::isValid($id)) {
            return null;
        }
        $group = $this->repository->find(new Ulid($id));

        return $group instanceof NodeGroup ? $this->outputFactory->create($group) : null;
    }
}
