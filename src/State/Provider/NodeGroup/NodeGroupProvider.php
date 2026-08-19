<?php

declare(strict_types=1);

namespace App\State\Provider\NodeGroup;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\NodeGroup\NodeGroupOutput;
use App\Entity\NodeGroup\NodeGroup;
use App\Repository\NodeGroup\NodeGroupRepository;
use App\Service\NodeGroup\NodeGroupOutputFactory;
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
