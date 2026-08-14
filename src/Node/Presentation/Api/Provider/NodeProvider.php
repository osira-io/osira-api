<?php

declare(strict_types=1);

namespace App\Node\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Node\Domain\Entity\Node;
use App\Node\Infrastructure\Repository\NodeRepository;
use App\Node\Presentation\Api\Factory\NodeOutputFactory;
use App\Node\Presentation\Api\Resource\NodeOutput;
use Symfony\Component\Uid\Ulid;

/** @implements ProviderInterface<NodeOutput> */
final readonly class NodeProvider implements ProviderInterface
{
    public function __construct(private NodeRepository $repository, private NodeOutputFactory $outputFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?NodeOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id) || !Ulid::isValid($id)) {
            return null;
        }

        $node = $this->repository->find(new Ulid($id));

        return $node instanceof Node ? $this->outputFactory->create($node) : null;
    }
}
