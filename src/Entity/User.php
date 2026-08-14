<?php

declare(strict_types=1);

namespace App\Entity;

use App\Internationalization\SupportedLocale;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    /** @var non-empty-string */
    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column]
    private string $password;

    /** @var list<string> */
    #[ORM\Column(name: 'roles', type: Types::JSON)]
    private array $technicalRoles;

    #[ORM\Column(length: 10, options: ['default' => SupportedLocale::EN])]
    private string $locale = SupportedLocale::EN;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'user_roles')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'role_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $businessRoles;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @param list<string> $roles */
    public function __construct(string $email, array $roles, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->email = self::normalizeEmail($email);
        $this->password = '';
        $this->technicalRoles = array_values(array_unique([...$roles, 'ROLE_USER']));
        $this->businessRoles = new ArrayCollection();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    /** @return non-empty-string */
    public static function normalizeEmail(string $email): string
    {
        $normalizedEmail = mb_strtolower(trim($email));
        if ('' === $normalizedEmail) {
            throw new \InvalidArgumentException('The email address cannot be empty.');
        }

        return $normalizedEmail;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->technicalRoles, 'ROLE_USER']));
    }

    /** @return Collection<int, Role> */
    public function businessRoles(): Collection
    {
        return $this->businessRoles;
    }

    /** @param iterable<Role> $roles */
    public function replaceBusinessRoles(iterable $roles, \DateTimeImmutable $updatedAt): void
    {
        $this->businessRoles->clear();
        foreach ($roles as $role) {
            if (!$this->businessRoles->contains($role)) {
                $this->businessRoles->add($role);
            }
        }
        $this->updatedAt = $updatedAt;
    }

    public function updateEmail(string $email, \DateTimeImmutable $updatedAt): void
    {
        $this->email = self::normalizeEmail($email);
        $this->updatedAt = $updatedAt;
    }

    public function setPasswordHash(string $passwordHash, \DateTimeImmutable $updatedAt): void
    {
        $this->password = $passwordHash;
        $this->updatedAt = $updatedAt;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function updateLocale(string $locale, \DateTimeImmutable $updatedAt): void
    {
        $this->locale = SupportedLocale::normalize($locale);
        $this->updatedAt = $updatedAt;
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
