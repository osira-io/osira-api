<?php

declare(strict_types=1);

namespace App\NodeGroup\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\NodeGroup\Domain\Entity\NodeGroup;
use App\NodeGroup\Infrastructure\Repository\NodeGroupRepository;
use App\NodeGroup\Presentation\Api\Factory\NodeGroupOutputFactory;
use App\NodeGroup\Presentation\Api\Resource\NodeGroupOutput;
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
