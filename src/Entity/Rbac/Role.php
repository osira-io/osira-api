<?php

declare(strict_types=1);

namespace App\Entity\Rbac;

use App\Repository\Rbac\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'roles')]
#[ORM\UniqueConstraint(name: 'uniq_roles_name', columns: ['name'])]
#[ORM\UniqueConstraint(name: 'uniq_roles_slug', columns: ['slug'])]
final class Role
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\Column(length: 128)]
    private string $name;

    #[ORM\Column(length: 128)]
    private readonly string $slug;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(options: ['default' => false])]
    private readonly bool $isSystem;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Permission> */
    #[ORM\ManyToMany(targetEntity: Permission::class)]
    #[ORM\JoinTable(name: 'role_permissions')]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'permission_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $permissions;

    public function __construct(string $name, string $slug, ?string $description, bool $isSystem, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->isSystem = $isSystem;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->permissions = new ArrayCollection();
    }

    public function update(string $name, ?string $description, \DateTimeImmutable $now): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->updatedAt = $now;
    }

    /** @param iterable<Permission> $permissions */
    public function replacePermissions(iterable $permissions, \DateTimeImmutable $now): void
    {
        $this->permissions->clear();
        foreach ($permissions as $permission) {
            if (!$this->permissions->contains($permission)) {
                $this->permissions->add($permission);
            }
        }
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

    public function slug(): string
    {
        return $this->slug;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    /** @return Collection<int, Permission> */
    public function permissions(): Collection
    {
        return $this->permissions;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
