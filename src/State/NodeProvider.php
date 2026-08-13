<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\NodeOutput;
use App\Entity\Node;
use App\Repository\NodeRepository;
use Symfony\Component\Uid\Ulid;

/** @implements ProviderInterface<NodeOutput> */
final readonly class NodeProvider implements ProviderInterface
{
    public function __construct(private NodeRepository $repository)
    {
    }

    /** @return NodeOutput|list<NodeOutput>|null */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            return array_map($this->toOutput(...), $this->repository->findAllOrdered());
        }

        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id) || !Ulid::isValid($id)) {
            return null;
        }

        $node = $this->repository->find(new Ulid($id));

        return $node instanceof Node ? $this->toOutput($node) : null;
    }

    private function toOutput(Node $node): NodeOutput
    {
        return new NodeOutput(
            (string) $node->id(),
            $node->hostname(),
            $node->displayName(),
            $node->os(),
            $node->architecture(),
            $node->firstSeenAt(),
            $node->createdAt(),
        );
    }
}
