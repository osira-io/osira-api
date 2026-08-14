<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NodeGroupRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: NodeGroupRepository::class)]
#[ORM\Table(name: 'node_groups')]
#[ORM\UniqueConstraint(name: 'uniq_node_groups_name', columns: ['name'])]
final class NodeGroup
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\Column(length: 128)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, ?string $description, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->name = $name;
        $this->description = $description;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(string $name, ?string $description, \DateTimeImmutable $now): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->updatedAt = $now;
    }
}
