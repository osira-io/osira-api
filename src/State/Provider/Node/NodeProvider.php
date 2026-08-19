<?php

declare(strict_types=1);

namespace App\State\Provider\Node;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Node\NodeOutput;
use App\Entity\Node\Node;
use App\Repository\Node\NodeRepository;
use App\Service\Node\NodeOutputFactory;
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
