<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'agent')]
#[ORM\Index(columns: ['node_id'], name: 'idx_agent_node')]
final class Agent
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\ManyToOne(targetEntity: Node::class, inversedBy: 'agents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private readonly Node $node;

    #[ORM\Column(length: 64)]
    private readonly string $version;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $installedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    public function __construct(Node $node, string $version, \DateTimeImmutable $installedAt, \DateTimeImmutable $createdAt)
    {
        $this->id = new Ulid();
        $this->node = $node;
        $this->version = $version;
        $this->installedAt = $installedAt;
        $this->createdAt = $createdAt;

        $node->addAgent($this);
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function node(): Node
    {
        return $this->node;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function installedAt(): \DateTimeImmutable
    {
        return $this->installedAt;
    }

    public function revokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function revoke(\DateTimeImmutable $revokedAt): void
    {
        $this->revokedAt ??= $revokedAt;
    }
}
